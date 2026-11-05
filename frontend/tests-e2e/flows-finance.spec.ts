import { test, expect } from "@playwright/test";
import { ApiClient, deepFind } from "./support/api";
import { TINY_PDF, created, dateFromNow, idOf, memberUserId, num, unique } from "./support/data";
import { firstArray } from "./support/ui";
import { XLSX_MIME, buildXlsx } from "./support/xlsx";

// Finance v2 (M5) as owner1, whose workspace has the finance_v2 flag on. The
// two-factor steps (issue and void with a stale check, voiding a receipt) are
// in flows-stepup.spec.ts, on the account that enrols two-factor.

const CUSTOMER_EMAIL = "freelancer1@demo.taqat.test";
const OTHER_CUSTOMER_EMAIL = "freelancer2@demo.taqat.test";
const MONEY = (value: number): string => value.toFixed(2);

let owner: ApiClient;
let customer: string;

test.beforeAll(async () => {
  owner = await ApiClient.login("owner1");
  customer = await memberUserId(owner, CUSTOMER_EMAIL);
});

test.afterAll(async () => {
  await owner.dispose();
});

async function field(path: string, key: string): Promise<unknown> {
  return deepFind(await owner.json(await owner.get(path)), key);
}

async function newCategory(): Promise<string> {
  const body = await created(owner, await owner.post("workspace/finance/catalog/categories", {
    kind: "sales", code: unique("CAT"), name_ar: "فئة اختبار", name_en: "Test category",
  }));
  return idOf(body);
}

async function newItem(categoryId: string, price = 7.5): Promise<string> {
  return idOf(await created(owner, await owner.post("workspace/finance/catalog/items", {
    catalog_category_id: categoryId, code: unique("ITM"), name_ar: "صنف اختبار", name_en: "Test item", unit: "piece", price,
  })));
}

async function draftInvoice(forCustomer: string, lines: unknown[], extra: Record<string, unknown> = {}): Promise<string> {
  return idOf(await created(owner, await owner.post("workspace/invoices/drafts", {
    customer_id: forCustomer, due_date: dateFromNow(14), lines, ...extra,
  })));
}

const plainLine = (amount: number) => ({ description: "E2E service", qty: "1.00", unit_price: MONEY(amount) });

async function issuedInvoice(forCustomer: string, amount: number): Promise<string> {
  const id = await draftInvoice(forCustomer, [plainLine(amount)]);
  const issued = await owner.post(`workspace/invoices/${id}/issue`);
  expect(issued.status(), "issuing a draft").toBe(200);
  return id;
}

test.describe("catalog (M5)", () => {
  test("an item is created, edited and archived, and archived items leave the default list", async () => {
    const categoryId = await newCategory();
    const itemId = await newItem(categoryId);

    const edited = await owner.put(`workspace/finance/catalog/items/${itemId}`, { price: 9.25, name_en: "Renamed item" });
    expect(edited.status()).toBe(200);
    expect(num(deepFind(await owner.json(edited), "price"))).toBeCloseTo(9.25, 2);

    expect((await owner.delete(`workspace/finance/catalog/items/${itemId}`)).status()).toBe(200);

    const active = firstArray(await owner.json(await owner.get("workspace/finance/catalog/items", { per_page: 200 }))) as Array<{ id: string }>;
    const archived = firstArray(await owner.json(await owner.get("workspace/finance/catalog/items", { archived: "only", per_page: 200 }))) as Array<{ id: string }>;
    expect(active.some((item) => item.id === itemId)).toBe(false);
    expect(archived.some((item) => item.id === itemId)).toBe(true);

    expect((await owner.delete(`workspace/finance/catalog/categories/${categoryId}`)).status(), "archiving the emptied category").toBe(200);
  });

  test("a code is unique inside the workspace and cannot be changed afterwards", async () => {
    const categoryId = await newCategory();
    const code = unique("DUP");
    const first = await owner.post("workspace/finance/catalog/items", { catalog_category_id: categoryId, code, name_ar: "أ", name_en: "A", unit: "piece", price: 1 });
    const second = await owner.post("workspace/finance/catalog/items", { catalog_category_id: categoryId, code, name_ar: "ب", name_en: "B", unit: "piece", price: 1 });
    const itemId = idOf(await owner.json(first));
    const rename = await owner.put(`workspace/finance/catalog/items/${itemId}`, { code: unique("NEW") });

    expect(first.status()).toBe(201);
    expect(second.status()).toBe(422);
    expect(rename.status()).toBe(422);
  });

  test("a negative price is refused", async () => {
    const categoryId = await newCategory();

    const response = await owner.post("workspace/finance/catalog/items", { catalog_category_id: categoryId, code: unique("NEG"), name_ar: "أ", name_en: "A", unit: "piece", price: -1 });

    expect(response.status()).toBe(422);
  });
});

test.describe("plans (M5)", () => {
  const plan = (overrides: Record<string, unknown> = {}) => ({
    code: unique("PLAN"), name_ar: "خطة اختبار", name_en: "Test plan", pricing_method: "monthly", price: 45, ...overrides,
  });

  test("plans of the flat, metered and bundle kinds are created, edited and archived", async () => {
    const monthly = idOf(await created(owner, await owner.post("workspace/finance/plans", plan())));
    await created(owner, await owner.post("workspace/finance/plans", plan({ pricing_method: "hourly", price: 2, overage_price: 3, included_quantity: 20 })));
    await created(owner, await owner.post("workspace/finance/plans", plan({ pricing_method: "bundle", price: 30, overage_price: 3, included_quantity: 10, validity_days: 30 })));

    const edited = await owner.put(`workspace/finance/plans/${monthly}`, plan({ price: 50, name_en: "Renamed plan", code: undefined }));
    expect(edited.status()).toBe(200);

    expect((await owner.post(`workspace/finance/plans/${monthly}/archive`)).status()).toBe(200);
    const archived = firstArray(await owner.json(await owner.get("workspace/finance/plans", { archived: "only", per_page: 200 }))) as Array<{ id: string }>;
    expect(archived.some((row) => row.id === monthly)).toBe(true);
  });

  test("a metered plan without an overage price and a dedicated hourly plan are refused", async () => {
    const noOverage = await owner.post("workspace/finance/plans", plan({ pricing_method: "hourly", included_quantity: 20 }));
    const dedicated = await owner.post("workspace/finance/plans", plan({ pricing_method: "hourly", overage_price: 3, included_quantity: 20, is_dedicated_seat: true }));

    expect(noOverage.status()).toBe(422);
    expect(dedicated.status()).toBe(422);
  });

  test("a promotional price must be lower than the price", async () => {
    const response = await owner.post("workspace/finance/plans", plan({ price: 40, promo_price: 50, promo_until: dateFromNow(30) }));

    expect(response.status()).toBe(422);
  });
});

test.describe("subscription financial terms (M5)", () => {
  async function subscriptionOf(email: string): Promise<string> {
    const roster = await owner.json(await owner.get("workspace/members", { search: email }));
    const row = firstArray(roster).find((item) => deepFind(item, "email") === email);
    return String((row as { subscription_id: string }).subscription_id);
  }

  test("terms are saved with a plan, and bad terms are refused", async () => {
    const planId = idOf(await created(owner, await owner.post("workspace/finance/plans", {
      code: unique("TERMS"), name_ar: "خطة", name_en: "Terms plan", pricing_method: "weekly", price: 12,
    })));
    const subscription = await subscriptionOf(OTHER_CUSTOMER_EMAIL);

    const saved = await owner.put(`workspace/subscriptions/${subscription}/financial`, { plan_id: planId, billing_mode: "prepaid", grace_days: 5, auto_renew: false });
    expect(saved.status()).toBe(200);
    const body = await owner.json(saved);
    expect(deepFind(body, "billing_mode")).toBe("prepaid");
    expect(num(deepFind(body, "grace_days"))).toBe(5);

    expect((await owner.put(`workspace/subscriptions/${subscription}/financial`, { billing_mode: "barter" })).status()).toBe(422);
    expect((await owner.put(`workspace/subscriptions/${subscription}/financial`, { grace_days: -1 })).status()).toBe(422);
  });
});

test.describe("unbilled charges (M5)", () => {
  const charge = (overrides: Record<string, unknown> = {}) => ({
    customer_id: customer, description: "E2E extra printing", qty: "2.00", unit_price: "5.00", ...overrides,
  });

  test("a charge is recorded as open and voiding it is final", async () => {
    const recorded = await created(owner, await owner.post("workspace/finance/unbilled-charges", charge()));
    const id = idOf(recorded);
    expect(deepFind(recorded, "status")).toBe("open");
    expect(num(deepFind(recorded, "amount"))).toBeCloseTo(10, 2);

    const voided = await owner.delete(`workspace/finance/unbilled-charges/${id}`);
    expect(voided.status()).toBe(200);
    expect(deepFind(await owner.json(voided), "status")).toBe("voided");
    expect((await owner.delete(`workspace/finance/unbilled-charges/${id}`)).status(), "voiding twice").toBeGreaterThanOrEqual(400);
  });

  test("a charge for someone who is not a customer of the workspace is refused", async () => {
    const response = await owner.post("workspace/finance/unbilled-charges", charge({ customer_id: "00000000-0000-4000-8000-000000000000" }));

    expect(response.status()).toBe(422);
  });

  test("an invoice takes the charge, and voiding the invoice gives it back", async () => {
    const chargeId = idOf(await created(owner, await owner.post("workspace/finance/unbilled-charges", charge({ description: "E2E charge to invoice" }))));
    const invoice = await draftInvoice(customer, [{ unbilled_charge_id: chargeId }]);

    expect((await owner.post(`workspace/invoices/${invoice}/issue`)).status()).toBe(200);
    const taken = firstArray(await owner.json(await owner.get("workspace/finance/unbilled-charges", { status: "invoiced", per_page: 200 }))) as Array<{ id: string }>;
    expect(taken.some((row) => row.id === chargeId), "the charge is invoiced").toBe(true);

    expect((await owner.post(`workspace/invoices/${invoice}/void`, { reason: "E2E void" })).status()).toBe(200);
    const open = firstArray(await owner.json(await owner.get("workspace/finance/unbilled-charges", { status: "open", per_page: 200 }))) as Array<{ id: string }>;
    expect(open.some((row) => row.id === chargeId), "the charge is open again").toBe(true);
  });
});

test.describe("invoice lifecycle (M5)", () => {
  test("a draft has no number and cannot take a payment", async () => {
    const id = await draftInvoice(customer, [plainLine(40)]);

    expect(await field(`workspace/invoices/${id}`, "doc_status")).toBe("draft");
    expect(await field(`workspace/invoices/${id}`, "invoice_number")).toBeNull();
    const payment = await owner.postFile(`workspace/invoices/${id}/payment`, { amount: "10.00" }, TINY_PDF);
    expect(payment.status(), "paying a draft").toBe(409);
  });

  test("a draft can be edited, issued once and then no longer edited", async () => {
    const id = await draftInvoice(customer, [plainLine(20)]);

    const edited = await owner.put(`workspace/invoices/${id}/draft`, { lines: [plainLine(20), plainLine(30)], due_date: dateFromNow(21) });
    expect(edited.status()).toBe(200);
    expect(num(deepFind(await owner.json(edited), "total"))).toBeCloseTo(50, 2);

    expect((await owner.post(`workspace/invoices/${id}/issue`)).status()).toBe(200);
    expect(String(await field(`workspace/invoices/${id}`, "invoice_number")).length).toBeGreaterThan(3);
    expect((await owner.post(`workspace/invoices/${id}/issue`)).status(), "issuing twice").toBe(409);
    expect((await owner.put(`workspace/invoices/${id}/draft`, { lines: [plainLine(5)] })).status(), "editing an issued invoice").toBe(409);
  });

  test("tax is added to the total", async () => {
    const id = await draftInvoice(customer, [plainLine(100)], { tax_rate: "10.00" });

    expect(num(await field(`workspace/invoices/${id}`, "tax_amount"))).toBeCloseTo(10, 2);
    expect(num(await field(`workspace/invoices/${id}`, "total"))).toBeCloseTo(110, 2);
  });

  test("voiding needs a reason, works on an issued invoice only, and a void invoice refuses payment", async () => {
    const draft = await draftInvoice(customer, [plainLine(15)]);
    expect((await owner.post(`workspace/invoices/${draft}/void`, { reason: "E2E void" })).status(), "voiding a draft").toBe(409);

    const id = await issuedInvoice(customer, 15);
    expect((await owner.post(`workspace/invoices/${id}/void`, {})).status(), "no reason").toBe(422);
    expect((await owner.post(`workspace/invoices/${id}/void`, { reason: "E2E void" })).status()).toBe(200);
    expect(await field(`workspace/invoices/${id}`, "doc_status")).toBe("void");

    expect((await owner.postFile(`workspace/invoices/${id}/payment`, { amount: "5.00" }, TINY_PDF)).status(), "paying a void invoice").toBe(409);
    expect((await owner.post(`workspace/invoices/${id}/void`, { reason: "again" })).status(), "voiding twice").toBe(409);
  });

  test("an invoice for someone who is not a customer is refused, and so is an empty one", async () => {
    const nobody = await owner.post("workspace/invoices/drafts", { customer_id: "00000000-0000-4000-8000-000000000000", due_date: dateFromNow(7), lines: [plainLine(5)] });
    const empty = await owner.post("workspace/invoices/drafts", { customer_id: customer, due_date: dateFromNow(7), lines: [] });

    expect(nobody.status()).toBe(422);
    expect(empty.status()).toBe(422);
  });

  test("the other workspace's owner does not see the invoice", async () => {
    const id = await issuedInvoice(customer, 12);
    const other = await ApiClient.login("owner2");
    const response = await other.get(`workspace/invoices/${id}`);
    await other.dispose();

    expect([403, 404]).toContain(response.status());
  });
});

test.describe("receipts (M5)", () => {
  const receipt = (invoices: Array<[string, number]>, amount: number, method = "cash") => ({
    customer_id: customer,
    method,
    amount: MONEY(amount),
    allocations: invoices.map(([invoice_id, part]) => ({ invoice_id, amount: MONEY(part) })),
  });

  test("one receipt allocated across two invoices pays both and the payment status follows", async () => {
    const first = await issuedInvoice(customer, 50);
    const second = await issuedInvoice(customer, 50);

    const made = await created(owner, await owner.post("workspace/finance/receipts", receipt([[first, 50], [second, 50]], 100)));

    expect(num(deepFind(made, "unallocated_amount"))).toBeCloseTo(0, 2);
    expect(await field(`workspace/invoices/${first}`, "payment_status")).toBe("paid");
    expect(await field(`workspace/invoices/${second}`, "payment_status")).toBe("paid");
  });

  test("a partial allocation leaves the invoices partly paid and a later allocation finishes one", async () => {
    const first = await issuedInvoice(customer, 50);
    const second = await issuedInvoice(customer, 50);
    const made = await created(owner, await owner.post("workspace/finance/receipts", receipt([[first, 30], [second, 30]], 100)));
    const id = idOf(made);

    expect(await field(`workspace/invoices/${first}`, "payment_status")).toBe("partial");
    expect(num(deepFind(made, "unallocated_amount"))).toBeCloseTo(40, 2);

    const more = await owner.post(`workspace/finance/receipts/${id}/allocations`, { allocations: [{ invoice_id: first, amount: "20.00" }] });
    expect(more.status()).toBe(200);
    expect(await field(`workspace/invoices/${first}`, "payment_status")).toBe("paid");
    expect(await field(`workspace/invoices/${second}`, "payment_status")).toBe("partial");
  });

  test("allocating more than the receipt holds or the invoice still owes is refused", async () => {
    const invoice = await issuedInvoice(customer, 50);

    const tooMuchForReceipt = await owner.post("workspace/finance/receipts", receipt([[invoice, 50]], 20));
    const tooMuchForInvoice = await owner.post("workspace/finance/receipts", receipt([[invoice, 80]], 100));

    expect(tooMuchForReceipt.status()).toBe(422);
    expect(tooMuchForInvoice.status()).toBe(422);
    expect(await field(`workspace/invoices/${invoice}`, "payment_status")).toBe("unpaid");
  });

  test("a draft or void invoice cannot be allocated", async () => {
    const draft = await draftInvoice(customer, [plainLine(10)]);

    const response = await owner.post("workspace/finance/receipts", receipt([[draft, 10]], 10));

    expect(response.status()).toBeGreaterThanOrEqual(400);
    expect(response.status()).toBeLessThan(500);
  });

  test("a cheque pays only when it clears, and bouncing it puts the invoice back", async () => {
    const invoice = await issuedInvoice(customer, 50);
    const made = await created(owner, await owner.post("workspace/finance/receipts", receipt([[invoice, 50]], 50, "cheque")));
    const id = idOf(made);

    expect(deepFind(made, "cheque_status")).toBe("received");
    expect(await field(`workspace/invoices/${invoice}`, "payment_status"), "uncollected cheque").toBe("unpaid");

    expect((await owner.put(`workspace/finance/receipts/${id}/cheque-status`, { status: "deposited" })).status()).toBe(200);
    expect((await owner.put(`workspace/finance/receipts/${id}/cheque-status`, { status: "cleared" })).status()).toBe(200);
    expect(await field(`workspace/invoices/${invoice}`, "payment_status"), "cleared cheque").toBe("paid");

    expect((await owner.put(`workspace/finance/receipts/${id}/cheque-status`, { status: "bounced" })).status()).toBe(200);
    expect(await field(`workspace/invoices/${invoice}`, "payment_status"), "bounced cheque").toBe("unpaid");
    expect((await owner.put(`workspace/finance/receipts/${id}/cheque-status`, { status: "cleared" })).status(), "a bounced cheque cannot move on").toBe(409);
  });

  test("the cheque status of a cash receipt cannot be changed", async () => {
    const made = await created(owner, await owner.post("workspace/finance/receipts", { customer_id: customer, method: "cash", amount: "5.00" }));

    const response = await owner.put(`workspace/finance/receipts/${idOf(made)}/cheque-status`, { status: "deposited" });

    expect(response.status()).toBe(422);
  });

  test("receipts are listed for the finance team and an unknown customer is refused", async () => {
    const list = await owner.get("workspace/finance/receipts", { per_page: 20 });
    const stranger = await owner.post("workspace/finance/receipts", { customer_id: "00000000-0000-4000-8000-000000000000", method: "cash", amount: "5.00" });

    expect(list.status()).toBe(200);
    expect(firstArray(await owner.json(list)).length).toBeGreaterThan(0);
    expect(stranger.status()).toBe(422);
  });
});

test.describe("credit notes (M5)", () => {
  test("the demo credit notes are listed, each linked to its invoice with a reason", async () => {
    const list = await owner.get("workspace/finance/credit-notes", { per_page: 100 });
    expect(list.status()).toBe(200);

    const notes = firstArray(await owner.json(list)) as Array<{ number: string; invoice_number: string | null; reason: string; amount: string }>;
    expect(notes.length).toBeGreaterThanOrEqual(2);
    for (const note of notes) {
      expect(note.invoice_number, "linked to an invoice").toBeTruthy();
      expect(note.reason.length, "carries a reason").toBeGreaterThan(2);
    }
  });

  test("a credit note needs the two-factor enrolment of the person issuing it", async () => {
    const invoice = await issuedInvoice(customer, 20);

    const response = await owner.post(`workspace/invoices/${invoice}/credit-notes`, { amount: "5.00", reason: "E2E" });

    expect(response.status()).toBe(403);
    expect(await response.json()).toMatchObject({ code: "two_factor_enrollment_required" });
  });
});

test.describe("expenses (M5)", () => {
  test("an expense can be voided with a reason, once", async () => {
    const made = await created(owner, await owner.post("workspace/expenses", {
      title: unique("E2E expense"), category: "other", amount: "12.50", spent_on: dateFromNow(-1),
    }));
    const id = idOf(made);

    expect((await owner.post(`workspace/expenses/${id}/void`, {})).status(), "no reason").toBe(422);
    const voided = await owner.post(`workspace/expenses/${id}/void`, { reason: "E2E void" });
    expect(voided.status()).toBe(200);
    expect(deepFind(await owner.json(voided), "voided_at")).toBeTruthy();
    expect((await owner.post(`workspace/expenses/${id}/void`, { reason: "again" })).status(), "voiding twice").toBeGreaterThanOrEqual(400);
  });
});

test.describe("POS products and the catalog (M5)", () => {
  test("with finance_v2 on, the catalog item is the source of the linked product's price", async () => {
    const item = await newItem(await newCategory(), 7);
    const product = await created(owner, await owner.post("pos/products", { name: unique("E2E product"), price: 99, catalog_item_id: item }));
    const productId = idOf(product);
    expect(num(deepFind(product, "price")), "the item's price wins at creation").toBeCloseTo(7, 2);

    const edited = await owner.put(`pos/products/${productId}`, { price: 50 });
    expect(edited.status()).toBe(200);
    expect(num(deepFind(await owner.json(edited), "price")), "the item keeps winning on edit").toBeCloseTo(7, 2);

    await owner.put(`workspace/finance/catalog/items/${item}`, { price: 8 });
    const products = firstArray(await owner.json(await owner.get("pos/products", { per_page: 200 }))) as Array<{ id: string; price: string }>;
    expect(num(products.find((row) => row.id === productId)?.price), "an item edit follows into the product").toBeCloseTo(8, 2);
  });
});

test.describe("POS products with finance_v2 off (M5)", () => {
  test("a product edit keeps its price", async () => {
    const other = await ApiClient.login("owner2");
    const product = await created(other, await other.post("pos/products", { name: unique("E2E product"), price: 5 }));
    const productId = idOf(product);

    const edited = await other.put(`pos/products/${productId}`, { price: 12 });
    const status = edited.status();
    const price = num(deepFind(await other.json(edited), "price"));
    await other.delete(`pos/products/${productId}`);
    await other.dispose();

    expect(status).toBe(200);
    expect(price).toBeCloseTo(12, 2);
  });
});

test.describe("XLSX import (M5)", () => {
  const ITEM_HEADERS = ["code", "category_code", "name_ar", "name_en", "unit", "price"];
  const WITHDRAWAL_HEADERS = ["source_ref", "customer_email", "description", "qty", "unit_price", "occurred_at"];
  const IMPORTS = "workspace/finance/imports";

  type Preview = { data: { id: string; status: string; errors: Array<{ row: number; column: string }> } };

  const upload = (type: string, rows: string[][]) =>
    owner.postUpload(`${IMPORTS}/preview`, "file", { type }, { name: "import.xlsx", mimeType: XLSX_MIME, buffer: buildXlsx(rows) });

  async function importCategory(): Promise<{ id: string; code: string }> {
    const code = unique("IMPCAT");
    const id = idOf(await created(owner, await owner.post("workspace/finance/catalog/categories", {
      kind: "sales", code, name_ar: "فئة استيراد", name_en: "Import category",
    })));
    return { id, code };
  }

  // Filtered by the new category, so the owner's growing catalog cannot push the rows off the page.
  async function itemCodes(categoryId: string): Promise<string[]> {
    const items = firstArray(await owner.json(await owner.get("workspace/finance/catalog/items", { catalog_category_id: categoryId, per_page: 200 }))) as Array<{ code: string }>;
    return items.map((item) => item.code);
  }

  test("a clean catalog file previews without writing, commits every row, and the same file is then refused", async () => {
    const { id: categoryId, code: category } = await importCategory();
    const codes = [unique("IMP"), unique("IMP"), unique("IMP")];
    const rows = [ITEM_HEADERS, ...codes.map((code) => [code, category, "صنف", "Imported item", "piece", "3.50"])];

    const previewed = await upload("catalog_items", rows);
    expect(previewed.status(), "previewing a clean file").toBe(201);
    const batch = (await owner.json<Preview>(previewed)).data.id;

    expect(await itemCodes(categoryId), "a preview writes nothing").toHaveLength(0);

    expect((await owner.post(`${IMPORTS}/${batch}/commit`)).status(), "committing").toBe(200);
    expect((await itemCodes(categoryId)).sort()).toEqual([...codes].sort());

    expect((await upload("catalog_items", rows)).status(), "the same file again").toBe(409);
    expect((await owner.post(`${IMPORTS}/${batch}/commit`)).status(), "committing twice").toBe(409);
  });

  test("one bad row shows by row and column, and the commit is refused", async () => {
    const { id: categoryId, code: category } = await importCategory();
    const good = unique("IMP");
    const rows = [ITEM_HEADERS, [good, category, "أ", "A", "piece", "1"], [unique("IMP"), category, "ب", "B", "bottle", "1"]];

    const previewed = await owner.json<Preview>(await upload("catalog_items", rows));
    expect(previewed.data.status).toBe("failed");
    expect(previewed.data.errors[0]).toMatchObject({ row: 3, column: "unit" });

    expect((await owner.post(`${IMPORTS}/${previewed.data.id}/commit`)).status()).toBe(422);
    expect(await itemCodes(categoryId), "the good row was not written either").toHaveLength(0);
  });

  test("a formula cell and a file over the row limit are refused", async () => {
    const { code: category } = await importCategory();

    expect((await upload("catalog_items", [ITEM_HEADERS, [unique("IMP"), category, "أ", "A", "piece", "=1+1"]])).status(), "a formula").toBe(422);

    const tooMany = Array.from({ length: 2001 }, (_, index) => [`ROW-${index}`, category, "أ", "A", "piece", "1"]);
    expect((await upload("catalog_items", [ITEM_HEADERS, ...tooMany])).status(), "2,001 rows").toBe(422);
  });

  test("withdrawals import as on-account charges and an imported source_ref cannot come in twice", async () => {
    const ref = unique("WD");
    const row = [ref, CUSTOMER_EMAIL, "Imported withdrawal", "2", "4.25", dateFromNow(-1)];
    const previewed = await upload("withdrawals", [WITHDRAWAL_HEADERS, row]);
    expect(previewed.status()).toBe(201);
    const batch = (await owner.json<Preview>(previewed)).data.id;

    expect((await owner.post(`${IMPORTS}/${batch}/commit`)).status()).toBe(200);
    const charges = firstArray(await owner.json(await owner.get("workspace/finance/unbilled-charges", { source: "import", customer_id: customer, per_page: 200 }))) as Array<{ source_ref: string; amount: string }>;
    expect(num(charges.find((charge) => charge.source_ref === ref)?.amount), "qty times unit price").toBeCloseTo(8.5, 2);

    const again = await owner.json<Preview>(await upload("withdrawals", [WITHDRAWAL_HEADERS, [ref, CUSTOMER_EMAIL, "Other file", "1", "1", ""]]));
    expect(again.data.status).toBe("failed");
    expect(again.data.errors[0].column).toBe("source_ref");
  });

  test("the templates download as XLSX files", async () => {
    for (const type of ["catalog_items", "withdrawals"]) {
      const response = await owner.get(`${IMPORTS}/template/${type}`);
      expect(response.status(), type).toBe(200);
      expect(response.headers()["content-type"]).toContain("spreadsheetml");
      expect((await response.body()).subarray(0, 2).toString(), "an XLSX is a zip").toBe("PK");
    }
  });
});
