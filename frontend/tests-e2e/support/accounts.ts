import { createHmac } from "node:crypto";
import path from "node:path";

/** Mirrors the accounts created by `php artisan demo:seed` (backend/app/Services/Demo). */
export const ACCOUNT_KEYS = [
  "admin",
  "admin_limited",
  "owner1",
  "owner2",
  "owner_throwaway",
  "owner_stepup",
  "staff_cashier",
  "staff_hospitality",
  "staff_finance",
  "freelancer1",
  "freelancer7",
] as const;

export type AccountKey = (typeof ACCOUNT_KEYS)[number];

export type Role = "admin" | "workspace_owner" | "freelancer" | "cashier";

export const DEMO_EMAIL_DOMAIN = "demo.taqat.test";

/** Accounts that enrol two-factor inside their own spec, so they get no stored session. */
export const OWN_SESSION_ACCOUNTS: readonly AccountKey[] = ["owner_throwaway", "owner_stepup"];

/** Accounts that get a stored session. */
export const SESSION_ACCOUNTS = ACCOUNT_KEYS.filter((key) => !OWN_SESSION_ACCOUNTS.includes(key));

export type ModuleFlag = "network" | "finance_v2" | "gsg";

/**
 * The module flags the demo seed turns on for each account's workspace (see
 * backend/app/Services/Demo/Steps/ModulesStep.php). Every account not listed
 * here, such as owner2, has all of them off.
 */
export const ENABLED_FLAGS: Partial<Record<AccountKey, readonly ModuleFlag[]>> = {
  owner1: ["network", "finance_v2"],
  owner_stepup: ["network", "finance_v2"],
  staff_cashier: ["network", "finance_v2"],
  staff_hospitality: ["network", "finance_v2"],
  staff_finance: ["network", "finance_v2"],
};

export function flagsOf(key: AccountKey): readonly ModuleFlag[] {
  return ENABLED_FLAGS[key] ?? [];
}

/**
 * The key of the demo "Sync probe router", derived from the demo password the
 * same way the seed derives it (DemoMarker::routerKeyFor). Never logged.
 */
export function probeRouterKey(): string {
  return createHmac("sha256", demoPassword()).update("demo-router-key").digest("hex");
}

export function roleOf(key: AccountKey): Role {
  if (key.startsWith("admin")) return "admin";
  if (key.startsWith("owner")) return "workspace_owner";
  if (key.startsWith("staff")) return "cashier";
  return "freelancer";
}

export function emailOf(key: AccountKey): string {
  return `${key.replace("_", ".")}@${DEMO_EMAIL_DOMAIN}`;
}

/** The dashboard each role lands on (see src/lib/auth.ts `dashboardFor`). */
export const DASHBOARD: Record<Role, string> = {
  admin: "/admin",
  workspace_owner: "/owner",
  freelancer: "/freelancer",
  cashier: "/cashier",
};

export function statePath(key: AccountKey): string {
  return path.join(__dirname, "..", ".auth", `${key}.json`);
}

/** Read from the environment only; it is never written anywhere or logged. */
export function demoPassword(): string {
  const password = process.env.E2E_DEMO_PASSWORD;
  if (!password) {
    throw new Error("E2E_DEMO_PASSWORD is not set. The workflow maps it from the STAGING_DEMO_PASSWORD secret.");
  }
  return password;
}

export function apiBase(): string {
  const base = process.env.E2E_API_URL;
  if (!base) {
    throw new Error("E2E_API_URL is not set (e.g. https://api.staging.taqat.space/api).");
  }
  return base.replace(/\/$/, "");
}
