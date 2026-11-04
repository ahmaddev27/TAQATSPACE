import { test, expect } from "@playwright/test";
import { apiBase, DASHBOARD, roleOf, statePath, type AccountKey, type Role, SESSION_ACCOUNTS } from "./support/accounts";
import { ApiClient } from "./support/api";
import { pagesFor, visit, watch } from "./support/walk";

const LOCALES = ["ar", "en"] as const;
const apiOrigin = (): string => new URL(apiBase()).origin;

// Every page in each role's menu, in both languages. Accounts whose workspace has a
// module on (support/accounts.ts ENABLED_FLAGS) also walk that module's pages.
for (const key of SESSION_ACCOUNTS) {
  test.describe(`page walk: ${key}`, () => {
    test.use({ storageState: statePath(key) });

    // One test per page: a slow or broken page fails alone and names itself.
    for (const locale of LOCALES) {
      for (const href of pagesFor(key)) {
        test(`/${locale}${href} loads`, async ({ page }) => {
          const problems = watch(page, apiOrigin());

          const found = await visit(page, `/${locale}${href}`, problems);

          expect(found, `problems on /${locale}${href} for ${key}`).toEqual([]);
        });
      }
    }
  });
}

// Each role is sent back to its own area when it opens another role's pages.
const FOREIGN_AREAS: Record<Role, string[]> = {
  admin: ["/owner", "/freelancer", "/cashier"],
  workspace_owner: ["/admin", "/freelancer", "/cashier"],
  freelancer: ["/admin", "/owner", "/cashier"],
  cashier: ["/admin", "/owner", "/freelancer"],
};
const REPRESENTATIVES: AccountKey[] = ["admin", "owner1", "freelancer1", "staff_cashier"];

test.describe("role isolation in the browser", () => {
  for (const key of REPRESENTATIVES) {
    test.describe(key, () => {
      test.use({ storageState: statePath(key) });

      test("other roles' areas redirect to its own dashboard", async ({ page }) => {
        const role = roleOf(key);
        for (const area of FOREIGN_AREAS[role]) {
          await page.goto(`/en${area}`);
          await expect(page, `${key} opening ${area}`).toHaveURL(new RegExp(`/en${DASHBOARD[role]}(/|$|\\?)`));
        }
      });
    });
  }
});

// The same boundary, enforced by the API itself.
test.describe("role isolation in the API", () => {
  const cases: Array<{ as: AccountKey; path: string }> = [
    { as: "freelancer1", path: "admin/approvals" },
    { as: "freelancer1", path: "workspace/audit-logs" },
    { as: "owner1", path: "admin/approvals" },
    { as: "owner1", path: "admin/users" },
    { as: "staff_cashier", path: "workspace/staff" },
    { as: "admin_limited", path: "admin/approvals" },
    { as: "admin_limited", path: "admin/audit-logs" },
    // Modules that are on for owner1's workspace are still the owner's alone.
    { as: "freelancer1", path: "workspace/routers" },
    { as: "owner1", path: "admin/hour-packages" },
    { as: "staff_cashier", path: "workspace/routers" },
  ];

  for (const { as, path } of cases) {
    test(`${as} is refused ${path}`, async () => {
      const api = await ApiClient.login(as);
      const response = await api.get(path);
      await api.dispose();
      expect(response.status()).toBe(403);
    });
  }
});
