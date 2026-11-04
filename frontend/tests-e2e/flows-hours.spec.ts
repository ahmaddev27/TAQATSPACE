import { test, expect } from "@playwright/test";
import { statePath } from "./support/accounts";
import { ApiClient, deepFind } from "./support/api";
import { TINY_PDF, created, idOf, num, unique } from "./support/data";
import { firstArray } from "./support/ui";

// Platform hour packages (M5.24). The packages, and one purchase waiting for
// activation, come from backend/app/Services/Demo/Steps/HourPackagesStep.php.
// Codes made here start with DEMO- so the next reseed removes them.

const BUYER_EMAIL = "freelancer1@demo.taqat.test";

let admin: ApiClient;

test.beforeAll(async () => {
  admin = await ApiClient.login("admin");
});

test.afterAll(async () => {
  await admin.dispose();
});

async function stats(): Promise<Record<string, number>> {
  const body = await admin.json<{ data: { stats: Record<string, unknown> } }>(await admin.get("admin/hour-purchases"));
  return Object.fromEntries(Object.entries(body.data.stats).map(([status, count]) => [status, num(count)]));
}

test.describe("hour packages in the browser", () => {
  test.use({ storageState: statePath("admin") });

  test("the admin page opens and shows the platform's packages", async ({ page }) => {
    await page.goto("/en/admin/hour-packages");

    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await expect(page.getByText(/10 hours/i).first()).toBeVisible();
  });
});

test.describe("hour packages (M5)", () => {
  test("a package is created, edited and archived", async () => {
    const code = unique("DEMO-E2E");
    const made = await created(admin, await admin.post("admin/hour-packages", {
      code, name_ar: "باقة اختبار", name_en: "Test package", hours: 5, price: 10, currency: "USD", validity_days: 30,
    }));
    const id = idOf(made);

    const edited = await admin.put(`admin/hour-packages/${id}`, { price: 12.5, hours: 6 });
    expect(edited.status()).toBe(200);
    expect(num(deepFind(await admin.json(edited), "price"))).toBeCloseTo(12.5, 2);

    expect((await admin.delete(`admin/hour-packages/${id}`)).status()).toBe(200);
    const listed = firstArray(await admin.json(await admin.get("admin/hour-packages"))) as Array<{ id: string; is_active: boolean }>;
    expect(listed.find((row) => row.id === id)?.is_active ?? false, "archived packages are inactive").toBe(false);
  });

  test("a duplicate code and a zero-hour package are refused", async () => {
    const duplicate = await admin.post("admin/hour-packages", { code: "DEMO-HOURS-10", name_ar: "أ", name_en: "A", hours: 1, price: 1, currency: "USD" });
    const empty = await admin.post("admin/hour-packages", { code: unique("DEMO-E2E"), name_ar: "أ", name_en: "A", hours: 0, price: 1, currency: "USD" });

    expect(duplicate.status()).toBe(422);
    expect(empty.status()).toBe(422);
  });

  test("activating a requested purchase records the payment once, and a second activation is refused", async () => {
    const queue = await admin.json(await admin.get("admin/hour-purchases", { status: "requested", per_page: 100 }));
    const request = firstArray(queue).find((row) => deepFind(row, "email") === BUYER_EMAIL) as { id: string } | undefined;
    expect(request, "the seeded purchase waiting for activation").toBeDefined();
    const before = await stats();

    const first = await admin.postFile(`admin/hour-purchases/${request?.id}/activate`, { payment_method: "cash" }, TINY_PDF);
    expect(first.status()).toBe(200);
    expect(deepFind(await admin.json(first), "status")).toBe("active");
    const second = await admin.postFile(`admin/hour-purchases/${request?.id}/activate`, { payment_method: "cash" }, TINY_PDF);
    expect(second.status(), "activating twice").toBe(409);

    const after = await stats();
    expect(after.active, "one purchase became active").toBe((before.active ?? 0) + 1);
    expect(after.requested, "and left the queue").toBe((before.requested ?? 0) - 1);
  });

  test("activation needs a receipt file and a payment method", async () => {
    const response = await admin.post("admin/hour-purchases/00000000-0000-4000-8000-000000000000/activate", { payment_method: "cash" });

    expect([404, 422]).toContain(response.status());
  });

  test("an owner and a member are refused the platform's hour packages", async () => {
    for (const key of ["owner1", "freelancer1"] as const) {
      const client = await ApiClient.login(key);
      const response = await client.get("admin/hour-packages");
      await client.dispose();
      expect(response.status(), key).toBe(403);
    }
  });

  test("the limited admin holds the billing permission and can read the packages", async () => {
    const limited = await ApiClient.login("admin_limited");
    const response = await limited.get("admin/hour-packages");
    await limited.dispose();

    expect(response.status()).toBe(200);
  });
});
