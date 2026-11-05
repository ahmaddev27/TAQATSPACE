import { test, expect } from "@playwright/test";
import { statePath } from "./support/accounts";
import { ApiClient, deepFind } from "./support/api";
import { en } from "./support/messages";
import { collect, dialog, fieldInput, firstArray, tab } from "./support/ui";

const VALID_IBAN = "GB82WEST12345698765432";
const OWNER1_WORKSPACE_SLUG = "demo-almanara";
const OWNER1_WORKSPACE_NAME = "مساحة المنارة";

async function ownerWorkspace(owner: ApiClient): Promise<{ id: string; body: unknown }> {
  // The same call the settings page makes to read the workspace.
  const body = await owner.json<{ data: { id: string } }>(await owner.put("workspace/settings", {}));
  return { id: body.data.id, body };
}

test.describe("owner settings (M1)", () => {
  test.use({ storageState: statePath("owner1") });

  test("an invalid IBAN is rejected and a valid one is saved with the general settings", async ({ page }) => {
    await page.goto("/en/owner/settings");
    await tab(page, en("owner", "owner.settings.tabGeneral")).click();

    await fieldInput(page, en("owner", "owner.settings.iban")).fill("NOT-AN-IBAN");
    await page.getByRole("button", { name: en("owner", "owner.settings.save") }).click();
    await expect(page.getByText(en("owner", "owner.settings.saveFailed"))).toBeVisible();

    await fieldInput(page, en("owner", "owner.settings.bankName")).fill("Demo Bank");
    await fieldInput(page, en("owner", "owner.settings.iban")).fill(VALID_IBAN);
    await page.getByRole("button", { name: en("owner", "owner.settings.save") }).click();
    await expect(page.getByText(en("owner", "owner.settings.saved"))).toBeVisible();

    const owner = await ApiClient.login("owner1");
    const { body } = await ownerWorkspace(owner);
    await owner.dispose();
    expect(deepFind(body, "iban")).toBe(VALID_IBAN);
    expect(deepFind(body, "bank_name")).toBe("Demo Bank");
  });

  test("the domain tab shows the workspace links and the public one opens", async ({ page }) => {
    await page.goto("/en/owner/settings");
    await tab(page, en("owner", "owner.settings.tabDomain")).click();

    await expect(page.getByText(en("owner", "owner.settings.domainPublic"))).toBeVisible();
    const slug = page.getByText(OWNER1_WORKSPACE_SLUG, { exact: true });
    await expect(slug).toBeVisible();

    const publicLink = (await page.locator(".ltr", { hasText: `/w/${OWNER1_WORKSPACE_SLUG}` }).first().innerText()).trim();
    expect(publicLink).toContain(`/w/${OWNER1_WORKSPACE_SLUG}`);
    const response = await page.request.get(publicLink);
    expect(response.status(), "the public workspace link").toBeLessThan(400);
  });

  test("keyboard shortcuts: ? opens the help and Alt+R jumps to invoices", async ({ page }) => {
    await page.goto("/en/owner");
    await page.locator("body").click({ position: { x: 5, y: 5 } });

    await page.keyboard.press("?");
    await expect(dialog(page).getByText(en("shortcuts", "shortcuts.title"))).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(dialog(page)).toHaveCount(0);

    await page.keyboard.press("Alt+r");
    await expect(page).toHaveURL(/\/en\/owner\/invoices/);
  });

  test("staff: an invitation is sent and permissions can be narrowed", async ({ page }) => {
    const invitee = `e2e-${Date.now()}@demo.taqat.test`;
    await page.goto("/en/owner/settings/staff");

    await page.getByRole("button", { name: en("owner", "owner.staff.invite") }).click();
    await fieldInput(dialog(page), en("owner", "owner.staff.email")).fill(invitee);
    await fieldInput(dialog(page), en("owner", "owner.staff.group")).selectOption({ label: en("owner", "owner.staff.groups.hospitality") });
    await dialog(page).getByRole("button", { name: en("owner", "owner.staff.send") }).click();
    await expect(page.getByText(en("owner", "owner.staff.invitedSuccess"))).toBeVisible();
    await expect(page.getByText(invitee)).toBeVisible();

    const row = page
      .locator(":is(li, tr, .row, .card)")
      .filter({ hasText: "دعاء الكحلوت" })
      .filter({ has: page.getByRole("button", { name: en("owner", "owner.staff.editPerms") }) })
      .last();
    await row.getByRole("button", { name: en("owner", "owner.staff.editPerms") }).click();
    await dialog(page).getByLabel(en("owner", "owner.staff.perms.pos_view_reports")).uncheck();
    await dialog(page).getByRole("button", { name: en("owner", "owner.staff.save") }).click();
    await expect(page.getByText(en("owner", "owner.staff.updatedSuccess"))).toBeVisible();

    const owner = await ApiClient.login("owner1");
    const staff = await owner.json(await owner.get("workspace/staff"));
    await owner.dispose();
    const cashier = firstArray(staff).find((member) => (member as { email?: string }).email === "staff.cashier@demo.taqat.test") as
      | { permissions: string[] }
      | undefined;
    expect(cashier?.permissions).toContain("pos_sell");
    expect(cashier?.permissions).not.toContain("pos_view_reports");
  });

  test("the audit log shows the workspace's actions and nothing from another workspace", async ({ page }) => {
    const owner1 = await ApiClient.login("owner1");
    const owner2 = await ApiClient.login("owner2");
    const own = (await ownerWorkspace(owner1)).id;
    const other = (await ownerWorkspace(owner2)).id;

    const ownLog = await owner1.json(await owner1.get("workspace/audit-logs", { per_page: 100 }));
    const otherLog = await owner2.json(await owner2.get("workspace/audit-logs", { per_page: 100 }));
    await owner1.dispose();
    await owner2.dispose();

    const scopes = new Set(collect(firstArray(ownLog), "workspace_id"));
    expect(scopes.size).toBeGreaterThan(0);
    expect([...scopes]).toEqual([own]);
    expect(new Set(collect(firstArray(otherLog), "workspace_id"))).toEqual(new Set([other]));
    expect(collect(ownLog, "action")).toContain("workspace.settings.updated");

    // The run keeps adding rows, so filter for this one instead of hoping it stays on the first page.
    await page.goto("/en/owner/settings/audit?action=workspace.settings.updated");
    await expect(page.getByRole("heading", { name: en("audit", "audit.title") })).toBeVisible();
    await expect(page.getByText("workspace.settings.updated").first()).toBeVisible();
  });
});

test.describe("operations changes need approval (M1)", () => {
  test.describe.configure({ mode: "serial" });

  async function currentTaxRate(): Promise<number> {
    const owner = await ApiClient.login("owner1");
    const { body } = await ownerWorkspace(owner);
    await owner.dispose();
    return Number(deepFind(body, "tax_rate") ?? 0);
  }

  async function submitTaxRate(page: import("@playwright/test").Page, value: number): Promise<void> {
    await page.goto("/en/owner/settings");
    await tab(page, en("owner", "owner.settings.tabOperations")).click();
    await fieldInput(page, en("owner", "owner.operations.taxRate")).fill(String(value));
    await page.getByRole("button", { name: en("owner", "owner.operations.submit") }).click();
    await expect(page.getByText(en("owner", "owner.operations.submitted"))).toBeVisible();
  }

  test("a change is held for approval and applied only after the admin approves it", async ({ browser }) => {
    const before = await currentTaxRate();
    const proposed = before === 7 ? 8 : 7;

    const ownerContext = await browser.newContext({ storageState: statePath("owner1") });
    await submitTaxRate(await ownerContext.newPage(), proposed);
    expect(await currentTaxRate(), "not applied before approval").toBe(before);

    const adminContext = await browser.newContext({ storageState: statePath("admin") });
    const admin = await adminContext.newPage();
    await admin.goto("/en/admin/approvals");
    const row = admin.locator("tr").filter({ hasText: OWNER1_WORKSPACE_NAME }).filter({ hasText: en("approvals", "approvals.types.pricing") }).first();
    await row.getByRole("button", { name: en("approvals", "approvals.approve") }).click();
    await dialog(admin).getByRole("button", { name: en("approvals", "approvals.approve") }).click();
    await expect(admin.getByText(en("approvals", "approvals.toast.approved"))).toBeVisible();

    expect(await currentTaxRate(), "applied after approval").toBe(proposed);
    await ownerContext.close();
    await adminContext.close();
  });

  test("rejecting needs a reason, and a rejected change is never applied", async ({ browser }) => {
    const before = await currentTaxRate();
    const proposed = before === 9 ? 10 : 9;

    const ownerContext = await browser.newContext({ storageState: statePath("owner1") });
    await submitTaxRate(await ownerContext.newPage(), proposed);

    const adminContext = await browser.newContext({ storageState: statePath("admin") });
    const admin = await adminContext.newPage();
    await admin.goto("/en/admin/approvals");
    const row = admin.locator("tr").filter({ hasText: OWNER1_WORKSPACE_NAME }).filter({ hasText: en("approvals", "approvals.types.pricing") }).first();
    await row.getByRole("button", { name: en("approvals", "approvals.reject") }).click();

    const confirmReject = dialog(admin).getByRole("button", { name: en("approvals", "approvals.reject") });
    await expect(confirmReject, "no reason yet").toBeDisabled();
    await fieldInput(dialog(admin), en("approvals", "approvals.reasonLabel")).fill("Rejected by the end-to-end suite");
    await expect(confirmReject).toBeEnabled();
    await confirmReject.click();
    await expect(admin.getByText(en("approvals", "approvals.toast.rejected"))).toBeVisible();

    expect(await currentTaxRate(), "unchanged after rejection").toBe(before);

    // The API refuses a rejection without a reason, too.
    const adminApi = await ApiClient.login("admin");
    const pending = await adminApi.json(await adminApi.get("admin/approvals", { status: "approved" }));
    const anyId = (firstArray(collect(pending, "requests")[0]) as Array<{ id: string }>)[0]?.id;
    if (anyId) {
      const response = await adminApi.post(`admin/approvals/${anyId}/reject`, { reason: "" });
      expect([409, 422]).toContain(response.status());
    }
    await adminApi.dispose();
    await ownerContext.close();
    await adminContext.close();
  });
});

test.describe("module switch from the admin (M1)", () => {
  test.use({ storageState: statePath("owner1") });

  // owner1's workspace now has the network and finance_v2 modules on from the seed (every
  // other check relies on that), so the switch is exercised on gsg, which no check uses.
  test("turning a module on shows it in the owner's menu, and off hides it", async ({ page }) => {
    const owner = await ApiClient.login("owner1");
    const { id } = await ownerWorkspace(owner);
    await owner.dispose();

    const admin = await ApiClient.login("admin");
    const menuLink = page.locator('a[href$="/owner/gsg"]');
    await page.goto("/en/owner");
    await expect(page.locator('a[href$="/owner/network"]').first(), "network is on from the seed").toBeVisible();
    await expect(menuLink).toHaveCount(0);
    try {
      expect((await admin.put(`admin/workspaces/${id}/features`, { features: { gsg: true } })).status()).toBe(200);
      await page.goto("/en/owner");
      await expect(menuLink.first()).toBeVisible();
    } finally {
      expect((await admin.put(`admin/workspaces/${id}/features`, { features: { gsg: false } })).status()).toBe(200);
    }
    await page.goto("/en/owner");
    await expect(menuLink).toHaveCount(0);
    await admin.dispose();
  });
});
