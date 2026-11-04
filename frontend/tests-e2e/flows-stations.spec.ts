import { test, expect } from "@playwright/test";
import { apiBase, statePath } from "./support/accounts";
import { ApiClient, deepFind } from "./support/api";
import { idOf, memberUserId, num, unique } from "./support/data";
import { en } from "./support/messages";
import { firstArray, tab } from "./support/ui";
import { visit, watch } from "./support/walk";

// The seeded second branch and its stations (backend/app/Services/Demo/Steps/StationsStep.php).
const SECOND_BRANCH = "فرع الشاطئ";

let owner: ApiClient;

test.beforeAll(async () => {
  owner = await ApiClient.login("owner1");
});

test.afterAll(async () => {
  await owner.dispose();
});

async function branches(): Promise<Array<{ id: string; name: string; is_default: boolean; seats_count?: number }>> {
  return firstArray(await owner.json(await owner.get("workspace/branches"))) as Array<{ id: string; name: string; is_default: boolean }>;
}

async function removeSeat(id: string): Promise<void> {
  expect([200, 204]).toContain((await owner.delete(`seats/${id}`)).status());
}

test.describe("stations in the browser (M4)", () => {
  test.use({ storageState: statePath("owner1") });

  test("the four station tabs open without errors and the branches tab lists the seeded branch", async ({ page }) => {
    const problems = watch(page, new URL(apiBase()).origin);
    expect(await visit(page, "/en/owner/seats", problems)).toEqual([]);

    for (const key of ["tabInventory", "tabNow", "tabMonth", "tabBranches"]) {
      const trigger = tab(page, en("owner", `owner.seats.stations.${key}`));
      await trigger.click();
      await expect(trigger).toHaveAttribute("aria-selected", "true");
    }

    await expect(page.getByText(SECOND_BRANCH).first()).toBeVisible();
    expect(problems.list).toEqual([]);
  });
});

test.describe("stations (M4)", () => {
  test("a bulk add creates the numbered stations in one call and refuses numbers that exist", async () => {
    const prefix = unique("B").replace(/-/g, "").slice(0, 10).toUpperCase();
    const payload = { start: 1, count: 2, prefix, pad: 2, type: "flexible", kind: "desk", zone: "E2E zone" };

    const response = await owner.post("workspace/seats/bulk", payload);
    expect([200, 201]).toContain(response.status());
    const seats = firstArray(await owner.json(response)) as Array<{ id: string; seat_number: string }>;
    expect(seats.map((seat) => seat.seat_number).sort()).toEqual([`${prefix}01`, `${prefix}02`]);

    const again = await owner.post("workspace/seats/bulk", payload);
    expect(again.status(), "the same numbers a second time").toBe(422);

    for (const seat of seats) await removeSeat(seat.id);
  });

  test("a bulk add past the workspace capacity is refused", async () => {
    const response = await owner.post("workspace/seats/bulk", { start: 1, count: 200, prefix: unique("X").slice(0, 8), type: "flexible" });

    expect(response.status()).toBe(422);
  });

  test("a station keeps its kind, zone and privacy, and the seat list can be filtered by zone", async () => {
    const number = unique("S").slice(0, 14);
    const created = await owner.post("workspace/seats", { seat_number: number, type: "private_office", kind: "room", zone: "E2E zone", capacity: 4, is_private: true });
    expect([200, 201]).toContain(created.status());
    const id = idOf(await owner.json(created));

    const zoned = firstArray(await owner.json(await owner.get("workspace/seats", { zone: "E2E zone" }))) as Array<{ seat_number: string; is_private: boolean; kind: string }>;
    const mine = zoned.find((seat) => seat.seat_number === number);
    expect(mine?.is_private).toBe(true);
    expect(mine?.kind).toBe("room");

    await removeSeat(id);
  });

  test("a branch is created, cannot be deleted while it holds a station, and can be once it is empty", async () => {
    const name = unique("Branch");
    const branch = await owner.post("workspace/branches", { name, address: "E2E street" });
    expect(branch.status()).toBe(201);
    const branchId = idOf(await owner.json(branch));

    const seat = await owner.post("workspace/seats", { seat_number: unique("S").slice(0, 14), type: "flexible", branch_id: branchId });
    const seatId = idOf(await owner.json(seat));
    expect((await owner.delete(`workspace/branches/${branchId}`)).status(), "a branch with a station").toBe(409);

    await removeSeat(seatId);
    expect((await owner.delete(`workspace/branches/${branchId}`)).status(), "the emptied branch").toBe(200);
    expect((await branches()).some((item) => item.id === branchId)).toBe(false);
  });

  test("the default branch and the seeded branch with stations cannot be deleted", async () => {
    const all = await branches();
    const defaultBranch = all.find((item) => item.is_default);
    const seeded = all.find((item) => item.name === SECOND_BRANCH);
    expect(defaultBranch, "a default branch").toBeDefined();
    expect(seeded, "the seeded branch").toBeDefined();

    expect((await owner.delete(`workspace/branches/${defaultBranch?.id}`)).status()).toBe(409);
    expect((await owner.delete(`workspace/branches/${seeded?.id}`)).status()).toBe(409);
  });

  test("another workspace's owner cannot delete this workspace's branch", async () => {
    const other = await ApiClient.login("owner2");
    const target = (await branches()).find((item) => item.name === SECOND_BRANCH);
    const response = await other.delete(`workspace/branches/${target?.id}`);
    await other.dispose();

    expect([403, 404]).toContain(response.status());
  });
});

test.describe("member profiles (M4)", () => {
  async function roster(profile?: "incomplete") {
    return owner.json(await owner.get("workspace/members", { per_page: 100, ...(profile ? { profile } : {}) }));
  }

  function completionOf(body: unknown, email: string): number | undefined {
    const row = firstArray(body).find((item) => deepFind(item, "email") === email);
    return row === undefined ? undefined : num(deepFind(row, "percent"));
  }

  test("the incomplete filter lists the member with missing fields and not the complete one", async () => {
    const incomplete = await roster("incomplete");
    const emails = new Set(firstArray(incomplete).map((item) => String(deepFind(item, "email"))));

    expect(emails.has("freelancer2@demo.taqat.test"), "a member with nothing filled in").toBe(true);
    expect(emails.has("freelancer1@demo.taqat.test"), "the complete member").toBe(false);
  });

  test("saving the donor fields raises the completeness and drops the member from the incomplete filter", async () => {
    const id = await memberUserId(owner, "freelancer2@demo.taqat.test");
    const before = completionOf(await roster(), "freelancer2@demo.taqat.test") ?? 100;

    const saved = await owner.put(`workspace/members/${id}/profile`, {
      gender: "male",
      education_level: "bachelor",
      specialization: "accounting",
      employment_status: "student",
    });
    expect(saved.status()).toBe(200);

    const after = completionOf(await roster(), "freelancer2@demo.taqat.test");
    expect(before).toBeLessThan(100);
    expect(after).toBe(100);
    const stillIncomplete = firstArray(await roster("incomplete")).some((item) => deepFind(item, "email") === "freelancer2@demo.taqat.test");
    expect(stillIncomplete).toBe(false);
  });

  test("a value outside the lists is refused", async () => {
    const id = await memberUserId(owner, "freelancer3@demo.taqat.test");

    const response = await owner.put(`workspace/members/${id}/profile`, { gender: "robot" });

    expect(response.status()).toBe(422);
  });

  test("a member of another workspace cannot be edited", async () => {
    const foreign = await ApiClient.login("owner2");
    const id = await memberUserId(owner, "freelancer1@demo.taqat.test");
    const response = await foreign.put(`workspace/members/${id}/profile`, { gender: "male" });
    await foreign.dispose();

    expect([403, 404]).toContain(response.status());
  });
});
