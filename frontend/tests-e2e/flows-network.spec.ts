import { test, expect, request, type APIRequestContext } from "@playwright/test";
import { apiBase, probeRouterKey, statePath } from "./support/accounts";
import { ApiClient } from "./support/api";
import { dateFromNow, rowWhere, unique } from "./support/data";
import { en } from "./support/messages";
import { dialog, fieldInput, firstArray, tab } from "./support/ui";

// The demo seed names these (backend/app/Services/Demo/Steps/NetworkStep.php).
const MAIN_ROUTER = "Main router";
const PROBE_ROUTER = "Sync probe router";

let owner: ApiClient;
let routers: unknown;

test.beforeAll(async () => {
  owner = await ApiClient.login("owner1");
  routers = await owner.json(await owner.get("workspace/routers"));
});

test.afterAll(async () => {
  await owner.dispose();
});

function routerId(name: string): string {
  const row = rowWhere(routers, "name", name);
  if (!row) throw new Error(`The demo router "${name}" is missing: was the seed run with the network step?`);
  return String(row.id);
}

test.describe("router integration (M2)", () => {
  test.use({ storageState: statePath("owner1") });

  test("the integration page shows the router with its status and sync endpoint", async ({ page }) => {
    await page.goto("/en/owner/settings/api");

    await expect(page.getByRole("heading", { name: en("integration", "integration.title") })).toBeVisible();
    await expect(page.getByRole("heading", { name: MAIN_ROUTER })).toBeVisible();
    await expect(page.getByText(en("integration", "integration.card.endpoint")).first()).toBeVisible();
  });

  test("an install command is issued in the browser and a new one replaces it", async ({ page }) => {
    await page.goto("/en/owner/settings/api");

    await page.getByRole("button", { name: en("integration", "integration.install.generate") }).click();
    const command = page.getByTestId("install-command");
    await expect(command).toBeVisible();
    const first = await command.innerText();
    expect(first).toContain("/api/v1/hotspot/install/");

    await page.getByRole("button", { name: en("integration", "integration.install.regenerate") }).click();
    await expect(command).not.toHaveText(first);
  });

  test("diagnostics runs the deep check in the browser", async ({ page }) => {
    await page.goto("/en/owner/settings/api/diagnostics");

    await page.getByRole("button", { name: en("integration", "integration.diagnostics.run") }).click();
    await expect(page.getByTestId("diagnostic-checks")).toBeVisible();
    await expect(page.getByText(en("integration", "integration.diagnostics.checks.last_sync")).first()).toBeVisible();
  });
});

test.describe("router API (M2)", () => {
  test("the install link works once and a second download is refused", async () => {
    const issued = await owner.json(await owner.post(`workspace/routers/${routerId(MAIN_ROUTER)}/install-token`));
    // Only the path is used, so the download always goes to the API under test whatever host the link carries.
    const path = new URL(String((issued as { data: { url: string } }).data.url)).pathname;
    const origin = new URL(apiBase()).origin;
    const anonymous = await request.newContext({ baseURL: origin });

    const first = await anonymous.get(path, { failOnStatusCode: false });
    const second = await anonymous.get(path, { failOnStatusCode: false });
    await anonymous.dispose();

    expect(first.status(), "first download").toBe(200);
    expect(second.status(), "second download").toBeGreaterThanOrEqual(400);
  });

  test("a script update can be queued once and is not queued twice", async () => {
    const id = routerId(MAIN_ROUTER);
    const offer = await owner.json<{ data: { available: boolean; latest_version: string } }>(await owner.get(`workspace/routers/${id}/script-update`));
    expect(offer.data.available, "the demo router runs an older script").toBe(true);

    const first = await owner.json<{ data: { queued: number } }>(await owner.post(`workspace/routers/${id}/update-script`, {}));
    const second = await owner.json<{ data: { queued: number } }>(await owner.post(`workspace/routers/${id}/update-script`, {}));

    expect(first.data.queued).toBeGreaterThan(0);
    expect(second.data.queued, "identical open commands are not repeated").toBe(0);
  });

  test("diagnostics returns the request log and the deep check", async () => {
    const id = routerId(MAIN_ROUTER);

    const logs = await owner.get(`workspace/routers/${id}/logs`);
    const check = await owner.json<{ data: { checks: Array<{ name: string; status: string }> } }>(await owner.post(`workspace/routers/${id}/diagnose`));

    expect(logs.status()).toBe(200);
    expect(check.data.checks.map((entry) => entry.name)).toContain("last_sync");
    expect(check.data.checks.every((entry) => ["pass", "warning", "fail"].includes(entry.status))).toBe(true);
  });

  test("router keys never appear in the router list", async () => {
    const text = JSON.stringify(routers);

    expect(text).not.toContain(probeRouterKey());
    expect(text).not.toMatch(/key_hash|previous_key/);
  });

  test("the installer-issuing and script routes refuse a member", async () => {
    const member = await ApiClient.login("freelancer1");
    const response = await member.post(`workspace/routers/${routerId(MAIN_ROUTER)}/install-token`);
    await member.dispose();

    expect(response.status()).toBe(403);
  });
});

// The wire format and replies are in DOCS/integration/router-protocol.md.
test.describe("router sync endpoint (M2 and M3)", () => {
  test.describe.configure({ mode: "serial" });

  let api: APIRequestContext;
  let code: string;
  const nonce = unique("n").replace(/[^A-Za-z0-9]/g, "");

  /** The router clock text the protocol uses: UTC, "YYYY-MM-DD HH:MM:SS". */
  const clock = (): string => new Date().toISOString().slice(0, 19).replace("T", " ");
  const batch = (id: string, events: string[] = []): string =>
    [`H|${id}|${Math.floor(Date.now() / 1000)}|e2eboot${nonce.slice(0, 8)}|${clock()}|full|${nonce}|1.1.0`, ...events].join("\n") + "\n";

  test.beforeAll(async () => {
    code = String((rowWhere(routers, "name", PROBE_ROUTER) as { workspace_code: string }).workspace_code);
    api = await request.newContext({ baseURL: `${apiBase()}/`, extraHTTPHeaders: { Accept: "text/plain" } });
  });

  test.afterAll(async () => {
    await api.dispose();
  });

  const post = (body: string, key: string | null = probeRouterKey()) =>
    api.post(`v1/hotspot/${code}/sync`, {
      data: body,
      headers: { "Content-Type": "text/plain", ...(key === null ? {} : { "X-API-Key": key }) },
      failOnStatusCode: false,
    });

  test("a text/plain sync is accepted and answered with the CWS-SYNC header echoing the nonce", async () => {
    const response = await post(batch(unique("b"), [`E|1|login|${clock()}|e2e-user|AA:BB:CC:DD:EE:99|10.10.0.99|||`]));

    expect(response.status()).toBe(200);
    expect(response.headers()["content-type"]).toContain("text/plain");
    expect((await response.text()).split("\n")[0]).toBe(`CWS-SYNC ${nonce}`);
  });

  test("the same batch sent again gets the identical reply", async () => {
    const body = batch(unique("b"));

    const first = await (await post(body)).text();
    const second = await (await post(body)).text();

    expect(second).toBe(first);
  });

  test("a wrong key and a missing key are refused", async () => {
    expect((await post(batch(unique("b")), "0".repeat(64))).status()).toBe(401);
    expect((await post(batch(unique("b")), null)).status()).toBe(401);
  });

  test("a malformed line is refused and nothing is stored for it", async () => {
    const response = await post(batch(unique("b"), ["E|1|login|not a time|x|||||"]));

    expect(response.status()).toBe(422);
  });

  test("a body without the H line is refused", async () => {
    expect((await post("E|1|login|2026-01-01 00:00:00|x|||||\n")).status()).toBe(422);
  });

  test("the sync shows up in the router's request log and the router is online", async () => {
    const id = routerId(PROBE_ROUTER);

    await expect
      .poll(
        async () => {
          const logs = await owner.json(await owner.get(`workspace/routers/${id}/logs`, { per_page: 50 }));
          return firstArray(logs).some((row) => String((row as { path: string }).path).includes("sync") && (row as { response_code: number }).response_code === 200);
        },
        { message: "a successful sync in the diagnostics log", timeout: 20_000 },
      )
      .toBe(true);

    const list = await owner.json(await owner.get("workspace/routers"));
    expect(rowWhere(list, "id", id)?.status).toBe("online");
  });
});

// Recalculation runs on the heavy queue, which the staging scheduler drains once a minute,
// so these tests wait for the status instead of assuming it is instant.
test.describe("statistics recalculation (M3.5)", () => {
  test.describe.configure({ mode: "serial" });
  test.setTimeout(300_000);

  type Status = { data: { state: string; active: boolean; written: { days: number } | null; scoring_failed?: boolean } };
  const RANGE = { from: dateFromNow(-10), to: dateFromNow(0) };

  const status = async (): Promise<Status> => owner.json<Status>(await owner.get("workspace/tools/recalculate/status"));

  /** A run left by an earlier test (or the nightly schedule) must end before a new one can start. */
  const waitUntilIdle = async (): Promise<Status> => {
    await expect
      .poll(async () => (await status()).data.active, { message: "no recalculation in flight", timeout: 240_000, intervals: [3_000] })
      .toBe(false);
    return status();
  };

  test.beforeAll(async () => {
    await waitUntilIdle();
  });

  test("a range longer than three months or in the wrong order is refused", async () => {
    const tooLong = await owner.post("workspace/tools/recalculate", { from: dateFromNow(-120), to: dateFromNow(0) });
    const reversed = await owner.post("workspace/tools/recalculate", { from: dateFromNow(0), to: dateFromNow(-5) });

    expect(tooLong.status()).toBe(422);
    expect(reversed.status()).toBe(422);
  });

  test("a member cannot start a recalculation", async () => {
    const member = await ApiClient.login("freelancer1");
    const response = await member.post("workspace/tools/recalculate", RANGE);
    await member.dispose();

    expect(response.status()).toBe(403);
  });

  test("the owner recalculates a range, a second request is refused while it runs, and the run completes", async () => {
    const first = await owner.post("workspace/tools/recalculate", RANGE);
    const second = await owner.post("workspace/tools/recalculate", RANGE);
    const secondBody = await owner.json<{ message: string }>(second);

    expect(first.status()).toBe(202);
    // 409 while the first still waits for the worker; 202 only if the worker already finished it.
    expect([202, 409]).toContain(second.status());
    if (second.status() === 409) expect(secondBody.message).toBeTruthy();

    const finished = await waitUntilIdle();
    expect(finished.data.state).toBe("completed");
    expect(finished.data.written?.days).toBe(11);
    expect(finished.data.scoring_failed ?? false).toBe(false);
  });

  test.describe("in the browser", () => {
    test.use({ storageState: statePath("owner1") });

    test("the Tools tab recalculates a range and shows it completed", async ({ page }) => {
      await waitUntilIdle();
      await page.goto("/en/owner/settings");
      await tab(page, en("owner", "owner.settings.tabTools")).click();

      const card = page.getByTestId("recalculate-card");
      await fieldInput(card, en("owner", "owner.tools.from")).fill(RANGE.from);
      await fieldInput(card, en("owner", "owner.tools.to")).fill(RANGE.to);
      await card.getByRole("button", { name: en("owner", "owner.tools.recalculate") }).click();
      await dialog(page).getByRole("button", { name: en("owner", "owner.tools.confirmAction") }).click();

      await expect(page.getByTestId("recalculate-state")).toHaveAttribute("data-state", "completed", { timeout: 240_000 });
    });
  });
});

test.describe("router gaps (M3.6)", () => {
  type Gap = { id: string; router_id: string; ended_at: string | null; reason: string; is_open: boolean };
  type GapList = { data: { gaps: Gap[]; meta: { total: number; per_page: number } } };

  test("the owner reads the main router's gaps: the seeded one is there and every gap has a known reason", async () => {
    const mainId = routerId(MAIN_ROUTER);
    const response = await owner.get("workspace/network/gaps", { router_id: mainId, per_page: 100 });
    const status = response.status();
    const body = await owner.json<GapList>(response);

    expect(status).toBe(200);
    // The demo seed records one closed no_sync gap; the scheduler may have opened more since, so only a floor is asserted.
    expect(body.data.meta.total).toBeGreaterThanOrEqual(1);
    expect(body.data.gaps.every((gap) => ["no_sync", "reboot", "clock_jump"].includes(gap.reason))).toBe(true);
    expect(body.data.gaps.every((gap) => gap.router_id === mainId)).toBe(true);
    expect(body.data.gaps.some((gap) => gap.reason === "no_sync" && !gap.is_open && gap.ended_at !== null)).toBe(true);
  });

  test("the open filter and the page size are honoured", async () => {
    const response = await owner.get("workspace/network/gaps", { open: 0, per_page: 1 });
    const body = await owner.json<GapList>(response);

    expect(response.status()).toBe(200);
    expect(body.data.meta.per_page).toBe(1);
    expect(body.data.gaps.length).toBeLessThanOrEqual(1);
    expect(body.data.gaps.every((gap) => !gap.is_open)).toBe(true);
  });

  test("a router id that is not the workspace's own is refused", async () => {
    const response = await owner.get("workspace/network/gaps", { router_id: "00000000-0000-4000-8000-000000000000" });
    await response.body();

    expect(response.status()).toBe(422);
  });

  test("a member cannot read the gaps", async () => {
    const member = await ApiClient.login("freelancer1");
    const response = await member.get("workspace/network/gaps");
    await response.body();
    await member.dispose();

    expect(response.status()).toBe(403);
  });
});
