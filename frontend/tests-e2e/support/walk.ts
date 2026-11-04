import type { Page, Response } from "@playwright/test";
import { ROLE_NAV, filterNavByPermissions, type NavItem } from "../../src/components/layout/nav-config";
import type { AccountKey } from "./accounts";
import { roleOf } from "./accounts";

/** Permissions each demo account holds (mirrors the backend demo steps). */
const PERMISSIONS: Partial<Record<AccountKey, string[]>> = {
  admin_limited: ["manage_workspaces", "manage_users", "manage_billing", "manage_content", "manage_messaging", "view_reports"],
  staff_cashier: ["pos_sell", "pos_view_reports"],
  staff_hospitality: ["pos_sell", "pos_manage_products"],
  staff_finance: ["pos_refund", "pos_view_reports"],
};

/** Pages reachable from the user menu rather than the sidebar. */
const EXTRA_PAGES: Record<string, string[]> = {
  admin: ["/admin/profile", "/settings/security"],
  workspace_owner: ["/owner/profile", "/owner/notifications", "/owner/bookings", "/settings/security"],
  freelancer: ["/freelancer/profile", "/freelancer/notifications"],
  cashier: ["/cashier/profile", "/settings/security"],
};

function leaves(items: NavItem[]): NavItem[] {
  return items.flatMap((item) => (item.children ? leaves(item.children) : [item]));
}

/**
 * Every page in the account's sidebar. Items behind a module flag or the
 * realtime-chat switch are left out: whether they exist depends on staging
 * configuration, and the specs cover them where it is known.
 */
export function pagesFor(key: AccountKey): string[] {
  const role = roleOf(key);
  const isSuper = key === "admin";
  const nav = filterNavByPermissions(ROLE_NAV[role], PERMISSIONS[key], isSuper);
  const hrefs = nav.groups
    .flatMap((group) => leaves(group.items))
    .filter((item) => item.href !== "#" && !item.feature && !item.realtime)
    .map((item) => item.href);

  return [...new Set([...hrefs, ...(EXTRA_PAGES[role] ?? [])])];
}

/** Text that only appears on a framework error page or the 404 page. */
const ERROR_PAGE = /Application error|Internal Server Error|This page could not be found|Server Error/i;

export interface PageProblems {
  list: string[];
}

/**
 * Starts recording what a page does wrong: uncaught browser errors and failed
 * calls to the API (or the app's own /api routes). Detach with the returned
 * function; the list is read after the page settled.
 */
export function watch(page: Page, apiOrigin: string): PageProblems {
  const problems: PageProblems = { list: [] };
  page.on("pageerror", (error) => problems.list.push(`uncaught error: ${error.message.slice(0, 200)}`));
  page.on("response", (response: Response) => {
    const url = response.url();
    const isOurs = url.startsWith(apiOrigin) || new URL(url).pathname.startsWith("/api/");
    if (isOurs && response.status() >= 400) {
      problems.list.push(`${response.status()} ${response.request().method()} ${new URL(url).pathname}`);
    }
  });
  return problems;
}

/** Opens a page and returns everything that is wrong with it (empty when healthy). */
export async function visit(page: Page, url: string, problems: PageProblems): Promise<string[]> {
  problems.list.length = 0;
  const response = await page.goto(url, { waitUntil: "load" });
  await page.waitForLoadState("networkidle", { timeout: 10_000 }).catch(() => undefined);

  const found = [...problems.list];
  if (!response || response.status() >= 400) found.push(`document responded ${response?.status() ?? "nothing"}`);
  if (ERROR_PAGE.test(await page.locator("body").innerText())) found.push("the page shows an error screen");
  return found;
}
