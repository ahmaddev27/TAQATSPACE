import { test, expect } from "@playwright/test";
import { ApiClient } from "./support/api";
import { dateFromNow, memberUserId, unique } from "./support/data";
import { firstArray } from "./support/ui";

// Staff hold only the finance permissions their group grants (backend
// FinanceAccess). The cashier group may collect and look at the POS, nothing more.
// The API is the boundary, so every refusal is checked there.

const UUID = "00000000-0000-4000-8000-000000000000";

let cashier: ApiClient;
// Real ids: the route binds the record before it checks the role, so an unknown id would answer 404 instead.
let invoiceId = "";
let expenseId = "";

test.beforeAll(async () => {
  cashier = await ApiClient.login("staff_cashier");
  const owner = await ApiClient.login("owner1");
  invoiceId = String((firstArray(await owner.json(await owner.get("workspace/invoices")))[0] as { id: string }).id);
  expenseId = String((firstArray(await owner.json(await owner.get("workspace/expenses")))[0] as { id: string }).id);
  await owner.dispose();
});

test.afterAll(async () => {
  await cashier.dispose();
});

test.describe("the cashier in a workspace with finance_v2 on", () => {
  test("may read and take receipts, which its group grants", async () => {
    expect((await cashier.get("workspace/finance/receipts")).status()).toBe(200);
  });

  test("is refused the catalog, plans and unbilled charges", async () => {
    const item = await cashier.post("workspace/finance/catalog/items", { catalog_category_id: UUID, code: unique("C"), name_ar: "أ", name_en: "A", unit: "piece", price: 1 });
    const category = await cashier.post("workspace/finance/catalog/categories", { kind: "sales", code: unique("C"), name_ar: "أ", name_en: "A" });
    const plan = await cashier.post("workspace/finance/plans", { code: unique("P"), name_ar: "أ", name_en: "A", pricing_method: "monthly", price: 1 });
    const charge = await cashier.post("workspace/finance/unbilled-charges", { customer_id: UUID, description: "x", qty: "1.00", unit_price: "1.00" });

    expect([item.status(), category.status(), plan.status(), charge.status()]).toEqual([403, 403, 403, 403]);
  });

  test("is refused issuing, voiding and the owner's invoice actions", async () => {
    const draft = await cashier.post("workspace/invoices/drafts", { customer_id: UUID, due_date: dateFromNow(7), lines: [] });
    const issue = await cashier.post(`workspace/invoices/${invoiceId}/issue`);
    const voided = await cashier.post(`workspace/invoices/${invoiceId}/void`, { reason: "E2E" });
    const receiptVoid = await cashier.post(`workspace/finance/receipts/${UUID}/void`, { reason: "E2E" });

    expect([draft.status(), issue.status(), voided.status(), receiptVoid.status()]).toEqual([403, 403, 403, 403]);
  });

  test("is refused the router and the platform's hour packages", async () => {
    expect((await cashier.get("workspace/routers")).status()).toBe(403);
    expect((await cashier.post(`workspace/routers/${UUID}/rotate-key`)).status()).toBe(403);
    expect((await cashier.get("admin/hour-packages")).status()).toBe(403);
  });

  test("cannot void an expense", async () => {
    expect((await cashier.post(`workspace/expenses/${expenseId}/void`, { reason: "E2E" })).status()).toBe(403);
  });
});

test.describe("a permission is what decides, not the title", () => {
  test("the finance supervisor holds more than the cashier and still needs two-factor to void a receipt", async () => {
    const supervisor = await ApiClient.login("staff_finance");
    const catalog = await supervisor.get("workspace/finance/catalog/items");
    const voided = await supervisor.post(`workspace/finance/receipts/${UUID}/void`, { reason: "E2E" });
    await supervisor.dispose();

    expect(catalog.status()).toBe(200);
    expect(voided.status(), "not enrolled in two-factor").toBe(403);
  });

  test("the owner sees the same roster whoever asks for the receipts", async () => {
    const owner = await ApiClient.login("owner1");
    const customer = await memberUserId(owner, "freelancer1@demo.taqat.test");
    const asOwner = firstArray(await owner.json(await owner.get("workspace/finance/receipts", { customer_id: customer, per_page: 100 })));
    const asCashier = firstArray(await cashier.json(await cashier.get("workspace/finance/receipts", { customer_id: customer, per_page: 100 })));
    await owner.dispose();

    expect(asCashier.length).toBe(asOwner.length);
  });
});
