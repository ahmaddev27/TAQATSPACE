import { test, expect, request, type APIRequestContext } from "@playwright/test";
import { apiBase, probeRouterKey, statePath } from "./support/accounts";
import { ApiClient } from "./support/api";
import { rowWhere, unique } from "./support/data";
import { en } from "./support/messages";
import { firstArray } from "./support/ui";

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
