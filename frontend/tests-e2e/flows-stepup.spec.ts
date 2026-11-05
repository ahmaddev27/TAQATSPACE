import { test, expect, request } from "@playwright/test";
import { apiBase, demoPassword, emailOf } from "./support/accounts";
import { ApiClient, deepFind } from "./support/api";
import { TINY_PDF, created, dateFromNow, idOf, memberUserId, unique } from "./support/data";
import { freshTotp } from "./support/totp";

// Everything that needs a recent two-factor check, on the one owner whose
// workspace has the modules on and who is free to enrol two-factor
// (owner_stepup). The two-factor state is kept per access token, so several
// tokens are taken before the account enrols: each is "stale" until it is
// verified, which makes the refusal testable without waiting out the 10-minute window.

const ACCOUNT = "owner_stepup" as const;
const CUSTOMER_EMAIL = "freelancer9@demo.taqat.test";

test.describe.configure({ mode: "serial", retries: 0 });
test.setTimeout(180_000);

let enroller: ApiClient;
let routerToken: ApiClient;
let invoiceToken: ApiClient;
let receiptToken: ApiClient;
let creditToken: ApiClient;
let secret = "";
let recoveryCodes: string[] = [];
let customer = "";

const TWO_FACTOR_REQUIRED = "two_factor_required";

/** A recovery code verifies a token without waiting for the next authenticator time step. */
async function verify(client: ApiClient): Promise<void> {
  const code = recoveryCodes.shift();
  if (!code) throw new Error("No recovery code left.");
  const response = await client.post("auth/two-factor/verify", { recovery_code: code });
  expect(response.status(), "two-factor verification").toBe(200);
}

test.beforeAll(async () => {
  [enroller, routerToken, invoiceToken, receiptToken, creditToken] = await Promise.all([
    ApiClient.login(ACCOUNT),
    ApiClient.login(ACCOUNT),
    ApiClient.login(ACCOUNT),
    ApiClient.login(ACCOUNT),
    ApiClient.login(ACCOUNT),
  ]);
  customer = await memberUserId(enroller, CUSTOMER_EMAIL);
});

test.afterAll(async () => {
  await Promise.all([enroller, routerToken, invoiceToken, receiptToken, creditToken].map((client) => client?.dispose()));
});

test("without two-factor, the sensitive actions are refused for enrolment, not for a stale check", async () => {
  const owner = await ApiClient.login("owner1");
  const routers = await owner.json<{ data: Array<{ id: string }> }>(await owner.get("workspace/routers"));
  const rotate = await owner.post(`workspace/routers/${routers.data[0].id}/rotate-key`);
  const voidReceipt = await owner.post("workspace/finance/receipts/00000000-0000-4000-8000-000000000000/void", { reason: "E2E" });
  const rotateBody = await rotate.json();
  await owner.dispose();

  expect(rotate.status()).toBe(403);
  expect(rotateBody).toMatchObject({ code: "two_factor_enrollment_required" });
  expect(voidReceipt.status()).toBe(403);
});

test("two-factor is enrolled through the API and gives recovery codes", async () => {
  const started = await enroller.json(await enroller.post("auth/two-factor/enable"));
  secret = String(deepFind(started, "secret"));
  expect(secret.length).toBeGreaterThan(10);

  const confirmed = await enroller.post("auth/two-factor/confirm", { code: await freshTotp(secret) });
  expect(confirmed.status()).toBe(200);
  recoveryCodes = (deepFind(await enroller.json(confirmed), "recovery_codes") as string[]) ?? [];
  expect(recoveryCodes.length).toBeGreaterThan(0);
});

test("a login now asks for a code", async () => {
  const context = await request.newContext({ baseURL: `${apiBase()}/`, extraHTTPHeaders: { Accept: "application/json" } });
  const response = await context.post("auth/login", { data: { email: emailOf(ACCOUNT), password: demoPassword() }, failOnStatusCode: false });
  const body = (await response.json()) as { data?: { two_factor_required?: boolean } };
  await context.dispose();

  expect(response.status()).toBe(200);
  expect(body.data?.two_factor_required).toBe(true);
});

test.describe("router key rotation (M2)", () => {
  let routerId = "";
  let code = "";

  test("rotating the key is refused until a code is entered, then works once", async () => {
    const made = await created(routerToken, await routerToken.post("workspace/routers", { name: unique("E2E router") }));
    routerId = idOf(made);
    code = String(deepFind(made, "workspace_code"));

    const stale = await routerToken.post(`workspace/routers/${routerId}/rotate-key`);
    expect(stale.status()).toBe(403);
    expect(await stale.json()).toMatchObject({ code: TWO_FACTOR_REQUIRED });

    await verify(routerToken);
    const rotated = await routerToken.post(`workspace/routers/${routerId}/rotate-key`);
    expect(rotated.status()).toBe(200);
    const newKey = String(deepFind(await rotated.json(), "key"));
    expect(newKey).toMatch(/^[0-9a-f]{48}$/);

    expect((await routerToken.post(`workspace/routers/${routerId}/rotate-key`)).status(), "a second rotation while the first is pending").toBe(409);

    const router = await request.newContext({ baseURL: `${apiBase()}/` });
    const body = `H|${unique("b")}|1|e2eboot${Date.now() % 100000}|${new Date().toISOString().slice(0, 19).replace("T", " ")}|full|stepupnonce|1.1.0\n`;
    const sync = await router.post(`v1/hotspot/${code}/sync`, { data: body, headers: { "Content-Type": "text/plain", "X-API-Key": newKey }, failOnStatusCode: false });
    const syncBody = await sync.text();
    await router.dispose();
    expect(sync.status(), "the new key syncs").toBe(200);
    expect(syncBody.startsWith("CWS-SYNC stepupnonce")).toBe(true);
  });
});

test.describe("invoices (M5)", () => {
  test("issuing needs a recent code, voiding works while it is fresh", async () => {
    const draft = idOf(await created(invoiceToken, await invoiceToken.post("workspace/invoices/drafts", {
      customer_id: customer, due_date: dateFromNow(14), lines: [{ description: "E2E step-up", qty: "1.00", unit_price: "60.00" }],
    })));

    const stale = await invoiceToken.post(`workspace/invoices/${draft}/issue`);
    expect(stale.status()).toBe(403);
    expect(await stale.json()).toMatchObject({ code: TWO_FACTOR_REQUIRED });

    await verify(invoiceToken);
    expect((await invoiceToken.post(`workspace/invoices/${draft}/issue`)).status()).toBe(200);
    expect((await invoiceToken.post(`workspace/invoices/${draft}/void`, { reason: "E2E void" })).status()).toBe(200);
  });
});

test.describe("receipts (M5)", () => {
  test("voiding a receipt needs a recent code and takes the money back off the invoice", async () => {
    // The enrolling token was verified when it confirmed, so it can issue; the receipt token has not been verified yet.
    const invoice = idOf(await created(enroller, await enroller.post("workspace/invoices/drafts", {
      customer_id: customer, due_date: dateFromNow(14), lines: [{ description: "E2E receipt", qty: "1.00", unit_price: "40.00" }],
    })));
    expect((await enroller.post(`workspace/invoices/${invoice}/issue`)).status()).toBe(200);

    const receipt = idOf(await created(receiptToken, await receiptToken.post("workspace/finance/receipts", {
      customer_id: customer, method: "cash", amount: "40.00", allocations: [{ invoice_id: invoice, amount: "40.00" }],
    })));
    const paid = await receiptToken.json(await receiptToken.get(`workspace/invoices/${invoice}`));
    expect(deepFind(paid, "payment_status")).toBe("paid");

    const stale = await receiptToken.post(`workspace/finance/receipts/${receipt}/void`, { reason: "E2E void" });
    expect(stale.status()).toBe(403);
    expect(await stale.json()).toMatchObject({ code: TWO_FACTOR_REQUIRED });

    await verify(receiptToken);
    expect((await receiptToken.post(`workspace/finance/receipts/${receipt}/void`, { reason: "E2E void" })).status()).toBe(200);
    const back = await receiptToken.json(await receiptToken.get(`workspace/invoices/${invoice}`));
    expect(deepFind(back, "payment_status")).toBe("unpaid");
  });

  test("a draft or void invoice refuses a payment even with a fresh check", async () => {
    const draft = idOf(await created(enroller, await enroller.post("workspace/invoices/drafts", {
      customer_id: customer, due_date: dateFromNow(14), lines: [{ description: "E2E draft", qty: "1.00", unit_price: "10.00" }],
    })));

    const response = await enroller.postFile(`workspace/invoices/${draft}/payment`, { amount: "5.00" }, TINY_PDF);

    expect(response.status()).toBe(409);
  });
});

test.describe("credit notes (M5)", () => {
  const issue = async (amount: string): Promise<string> => {
    const draft = idOf(await created(enroller, await enroller.post("workspace/invoices/drafts", {
      customer_id: customer, due_date: dateFromNow(14), lines: [{ description: "E2E credit", qty: "1.00", unit_price: amount }],
    })));
    expect((await enroller.post(`workspace/invoices/${draft}/issue`)).status()).toBe(200);
    return draft;
  };

  test("issuing needs a recent code; the invoice itself stays as it was and the receivable follows", async () => {
    const invoice = await issue("50.00");

    const stale = await creditToken.post(`workspace/invoices/${invoice}/credit-notes`, { amount: "50.00", reason: "E2E credit" });
    expect(stale.status()).toBe(403);
    expect(await stale.json()).toMatchObject({ code: TWO_FACTOR_REQUIRED });

    await verify(creditToken);
    const made = await creditToken.post(`workspace/invoices/${invoice}/credit-notes`, { amount: "50.00", reason: "E2E credit" });
    expect(made.status()).toBe(201);
    const note = await creditToken.json(made);
    expect(deepFind(note, "number")).toBeTruthy();
    expect(Number(deepFind(note, "refund_amount"))).toBeCloseTo(0, 2);

    const after = await creditToken.json(await creditToken.get(`workspace/invoices/${invoice}`));
    expect(Number(deepFind(after, "amount")), "the invoice total is untouched").toBeCloseTo(50, 2);
    expect(Number(deepFind(after, "credited_amount"))).toBeCloseTo(50, 2);
    expect(Number(deepFind(after, "remaining_amount")), "nothing left to collect").toBeCloseTo(0, 2);
    expect(deepFind(after, "doc_status")).toBe("issued");
  });

  test("a credit above what the invoice carries, an empty reason and a draft are refused", async () => {
    const invoice = await issue("30.00");
    const draft = idOf(await created(enroller, await enroller.post("workspace/invoices/drafts", {
      customer_id: customer, due_date: dateFromNow(14), lines: [{ description: "E2E draft", qty: "1.00", unit_price: "10.00" }],
    })));

    expect((await enroller.post(`workspace/invoices/${invoice}/credit-notes`, { amount: "30.01", reason: "Too much" })).status()).toBe(422);
    expect((await enroller.post(`workspace/invoices/${invoice}/credit-notes`, { amount: "5.00", reason: "" })).status()).toBe(422);
    expect((await enroller.post(`workspace/invoices/${draft}/credit-notes`, { amount: "5.00", reason: "On a draft" })).status()).toBe(422);
  });
});

test("a plan of this workspace cannot be attached to the first workspace's subscription", async () => {
  const plan = idOf(await created(enroller, await enroller.post("workspace/finance/plans", {
    code: unique("FOREIGN"), name_ar: "خطة", name_en: "Foreign plan", pricing_method: "daily", price: 3,
  })));
  const owner = await ApiClient.login("owner1");
  const roster = await owner.json(await owner.get("workspace/members", { search: "freelancer2@demo.taqat.test" }));
  const subscription = String(deepFind(roster, "subscription_id"));

  const response = await owner.put(`workspace/subscriptions/${subscription}/financial`, { plan_id: plan });
  await owner.dispose();

  expect(response.status()).toBe(422);
});
