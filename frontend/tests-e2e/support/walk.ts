import type { Page, Response } from "@playwright/test";
import { ROLE_NAV, filterNavByFeatures, filterNavByPermissions, type NavItem } from "../../src/components/layout/nav-config";
import type { AccountKey, ModuleFlag } from "./accounts";
import { flagsOf, roleOf } from "./accounts";

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

/** Pages a flag opens that are not in the sidebar. */
const FLAG_EXTRA_PAGES: Partial<Record<ModuleFlag, string[]>> = {
  network: ["/owner/settings/api/diagnostics"],
};

/**
 * Menu entries whose page is not built yet: the sidebar links to them as soon as
 * the flag is on, and they answer 404. Kept out of the walk so it checks what
 * exists; remove an entry when its page ships.
 */
export const PAGES_NOT_BUILT_YET: readonly string[] = ["/owner/network", "/owner/gsg"];

function leaves(items: NavItem[]): NavItem[] {
  return items.flatMap((item) => (item.children ? leaves(item.children) : [item]));
}

function flagMap(key: AccountKey): Record<ModuleFlag, boolean> {
  const on = flagsOf(key);
  return { network: on.includes("network"), finance_v2: on.includes("finance_v2"), gsg: on.includes("gsg") };
}

/**
 * Every page in the account's sidebar, including the ones its workspace's module
 * flags open (see ENABLED_FLAGS). Only the realtime-chat switch is left out:
 * whether chat or messages shows depends on staging configuration.
 */
export function pagesFor(key: AccountKey): string[] {
  const role = roleOf(key);
  const isSuper = key === "admin";
  const byPermission = filterNavByPermissions(ROLE_NAV[role], PERMISSIONS[key], isSuper);
  const nav = filterNavByFeatures(byPermission, flagMap(key));
  const hrefs = nav.groups
    .flatMap((group) => leaves(group.items))
    .filter((item) => item.href !== "#" && !item.realtime && !PAGES_NOT_BUILT_YET.includes(item.href))
    .map((item) => item.href);
  const flagPages = role === "workspace_owner" ? flagsOf(key).flatMap((flag) => FLAG_EXTRA_PAGES[flag] ?? []) : [];

  return [...new Set([...hrefs, ...flagPages, ...(EXTRA_PAGES[role] ?? [])])];
}

/** The pages of modules the account's workspace has switched off: the menu hides them and the routes refuse them. */
export function gatedPagesFor(key: AccountKey): string[] {
  const role = roleOf(key);
  const on = flagMap(key);
  const hrefs = ROLE_NAV[role].groups
    .flatMap((group) => leaves(group.items))
    .filter((item) => item.feature !== undefined && !on[item.feature])
    .map((item) => item.href);
  const flagPages = role === "workspace_owner" ? (Object.keys(FLAG_EXTRA_PAGES) as ModuleFlag[]).filter((flag) => !on[flag]).flatMap((flag) => FLAG_EXTRA_PAGES[flag] ?? []) : [];

  return [...new Set([...hrefs, ...flagPages])].filter((href) => !PAGES_NOT_BUILT_YET.includes(href));
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
  // A short window for late API calls: the chat's Firestore listener never lets the network go idle.
  await page.waitForLoadState("networkidle", { timeout: 3_000 }).catch(() => undefined);

  const found = [...problems.list];
  if (!response || response.status() >= 400) found.push(`document responded ${response?.status() ?? "nothing"}`);
  if (ERROR_PAGE.test(await page.locator("body").innerText())) found.push("the page shows an error screen");
  return found;
}
