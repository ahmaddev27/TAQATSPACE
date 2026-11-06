import { test, expect } from "@playwright/test";
import { statePath } from "./support/accounts";
import { ApiClient, deepFind } from "./support/api";
import { created, dateFromNow, idOf, unique } from "./support/data";
import { firstArray } from "./support/ui";
import { gatedPagesFor } from "./support/walk";

// owner2's workspace has no module flag on (backend/app/Services/Demo/Steps/ModulesStep.php):
// the menu hides the gated pages, their routes are refused, and the pages it
// already had behave as before. The pages themselves are walked in pages.spec.ts.

const NOT_FOUND = /could not be found|404/i;
const UUID = "00000000-0000-4000-8000-000000000000";

test.describe("modules off, in the browser", () => {
  test.use({ storageState: statePath("owner2") });

  test("the menu has none of the gated pages", async ({ page }) => {
    await page.goto("/en/owner");

    const gated = gatedPagesFor("owner2");
    expect(gated.length, "the gated pages come from the menu definition").toBeGreaterThan(0);
    for (const href of gated) {
      await expect(page.locator(`a[href$="${href}"]`), `menu link to ${href}`).toHaveCount(0);
    }
  });

  for (const href of gatedPagesFor("owner2")) {
    test(`${href} is not found`, async ({ page }) => {
      await page.goto(`/en${href}`);

      // These pages stream: the response is a 200 and the 404 arrives after it, so wait for the text.
      await expect(page.locator("body"), `${href} must not open while its module is off`).toContainText(NOT_FOUND);
    });
  }
});

test.describe("modules off, in the API", () => {
  let owner: ApiClient;

  test.beforeAll(async () => {
    owner = await ApiClient.login("owner2");
  });

  test.afterAll(async () => {
    await owner.dispose();
  });

  test("finance v2 endpoints answer 404, as if they did not exist", async () => {
    const reads = ["workspace/finance/catalog/items", "workspace/finance/catalog/categories", "workspace/finance/plans", "workspace/finance/receipts", "workspace/finance/credit-notes", "workspace/finance/wallet", "workspace/finance/payment-vouchers", "workspace/finance/unbilled-charges", "workspace/finance/imports/template/catalog_items"];
    for (const path of reads) {
      expect((await owner.get(path)).status(), path).toBe(404);
    }

    // Real ids, so a 404 can only come from the module switch and not from a record that does not exist.
    const invoice = (firstArray(await owner.json(await owner.get("workspace/invoices")))[0] as { id: string }).id;
    const subscription = (firstArray(await owner.json(await owner.get("workspace/subscriptions")))[0] as { id: string }).id;

    expect((await owner.post("workspace/invoices/drafts", { customer_id: UUID, due_date: dateFromNow(7), lines: [] })).status()).toBe(404);
    expect((await owner.put(`workspace/subscriptions/${subscription}/financial`, { grace_days: 1 })).status()).toBe(404);
    expect((await owner.post(`workspace/invoices/${invoice}/issue`)).status()).toBe(404);
    expect((await owner.post(`workspace/invoices/${invoice}/credit-notes`, { amount: "1.00", reason: "E2E" })).status()).toBe(404);
    expect((await owner.post(`workspace/finance/wallet/${UUID}/deposits`, { amount: "1.00", method: "cash" })).status()).toBe(404);
  });

  test("network endpoints are forbidden", async () => {
    expect((await owner.get("workspace/routers")).status()).toBe(403);
    expect((await owner.post("workspace/routers", { name: "Not allowed" })).status()).toBe(403);
    expect((await owner.get(`workspace/routers/${UUID}/logs`)).status()).toBe(403);
    expect((await owner.post(`workspace/routers/${UUID}/diagnose`)).status()).toBe(403);
  });

  test("the feature map the app reads says every module is off, and says both are on for owner1", async () => {
    const features = deepFind(await owner.json(await owner.get("auth/me")), "features");
    const flagged = await ApiClient.login("owner1");
    const flaggedFeatures = deepFind(await flagged.json(await flagged.get("auth/me")), "features");
    await flagged.dispose();

    expect(features).toEqual({ network: false, finance_v2: false, gsg: false });
    expect(flaggedFeatures).toMatchObject({ network: true, finance_v2: true });
  });

  test("the invoice list and the expense list work as before, and deleting an expense removes it", async () => {
    expect((await owner.get("workspace/invoices")).status()).toBe(200);

    const made = await created(owner, await owner.post("workspace/expenses", { title: unique("E2E expense"), category: "other", amount: "9.00", spent_on: dateFromNow(-1) }));
    const id = idOf(made);
    expect((await owner.delete(`workspace/expenses/${id}`)).status()).toBe(200);

    const rows = firstArray(await owner.json(await owner.get("workspace/expenses", { per_page: 100 }))) as Array<{ id: string }>;
    expect(rows.some((row) => row.id === id), "a hard delete, not a void").toBe(false);
  });
});
