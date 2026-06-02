import type { UserRole } from "./types/auth";

/** httpOnly bearer-token cookie (set only by Route Handlers / Server Actions). */
export const TOKEN_COOKIE = "taqat_token";

/** Non-httpOnly companion cookie so middleware can route by role. */
export const ROLE_COOKIE = "taqat_role";

/** 7 days, in seconds. */
export const TOKEN_MAX_AGE = 60 * 60 * 24 * 7;

const DASHBOARD_BY_ROLE: Record<UserRole, string> = {
  workspace_owner: "/owner",
  freelancer: "/freelancer",
  admin: "/admin",
};

/** Resolve the dashboard path for a given role (defaults to freelancer). */
export function dashboardFor(role: string | undefined | null): string {
  if (role && role in DASHBOARD_BY_ROLE) {
    return DASHBOARD_BY_ROLE[role as UserRole];
  }
  return DASHBOARD_BY_ROLE.freelancer;
}

export function isKnownRole(role: string | undefined | null): role is UserRole {
  return !!role && role in DASHBOARD_BY_ROLE;
}
