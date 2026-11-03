export type UserRole = "freelancer" | "workspace_owner" | "admin" | "cashier";

export type UserStatus = "active" | "suspended" | "pending_verification";

/** Self-reported gender; null on the user means "unspecified". */
export type Gender = "male" | "female";

/** Spatie roles an admin account may hold (distinct from the `role` column). */
export type AdminRole = "super_admin" | "admin";

/** Named permissions that gate the major admin areas. */
export type AdminPermission =
  | "manage_admins"
  | "manage_workspaces"
  | "manage_users"
  | "manage_billing"
  | "manage_content"
  | "manage_messaging"
  | "view_reports"
  | "approve_requests";

/** Named permissions that gate the POS (café) area for a cashier account. */
export type PosPermission =
  | "pos_sell"
  | "pos_refund"
  | "pos_manage_products"
  | "pos_view_reports";

/** Modules rolled out per workspace; every flag is off until a super admin enables it. */
export type WorkspaceFeature = "network" | "finance_v2" | "gsg";

export type WorkspaceFeatureFlags = Record<WorkspaceFeature, boolean>;

export interface User {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  /** Self-reported gender; null means "unspecified". */
  gender: Gender | null;
  role: UserRole;
  status: UserStatus;
  /** True for a freshly-provisioned SSO account that must still pick a role. */
  needs_onboarding: boolean;
  specialty: string | null;
  bio: string | null;
  avatar: string | null;
  /** True only for the local email/password admin; false for SSO accounts. */
  can_change_password: boolean;
  /** Owners, staff and admins may enroll in TOTP two-factor authentication. */
  can_use_two_factor?: boolean;
  two_factor_enabled?: boolean;
  email_verified_at: string | null;
  created_at: string;
  /** Present for admin accounts only: holds the elevated super-admin role. */
  is_super_admin?: boolean;
  /**
   * Effective permission names. Admin accounts carry their admin-area grants;
   * cashier accounts carry their POS grants (e.g. `pos_refund`). Absent for
   * roles with no permission tier (owner/freelancer).
   */
  permissions?: (AdminPermission | PosPermission)[];
  /** Feature flags of the account's workspace; absent for admins and freelancers. */
  features?: WorkspaceFeatureFlags;
  /**
   * A pending cashier invitation matching this account's email, surfaced during
   * onboarding so the user can Accept/Decline. Present only while
   * `needs_onboarding` is true; `null` when there is no open invitation.
   */
  pending_cashier_invitation?: CashierInvitationOffer | null;
}

/** A pending cashier invitation offered to a signed-in user during onboarding. */
export interface CashierInvitationOffer {
  id: string;
  workspace_name: string | null;
  permissions: string[];
}

/** Backend success envelope: { data, message? }. */
export interface ApiEnvelope<T> {
  data: T;
  message?: string;
}

/** Backend error envelope: { message, errors? }. */
export interface ApiErrorBody {
  message: string;
  errors?: Record<string, string[]>;
}

export interface AuthPayload {
  user: User;
  token: string;
  token_type: string;
  role: UserRole;
}

/** Backend payload when the account has 2FA: only a challenge-only token is issued. */
export interface TwoFactorChallengePayload {
  two_factor_required: true;
  token: string;
  token_type: string;
}

/** What our Next route handlers expose to the client (never the token). */
export interface ClientAuthResult {
  user: User;
  role: UserRole;
}

/** Client-side outcome of a sign-in attempt that may require a 2FA challenge. */
export interface ClientTwoFactorRequired {
  two_factor_required: true;
}

export function isTwoFactorRequired(
  result: ClientAuthResult | ClientTwoFactorRequired,
): result is ClientTwoFactorRequired {
  return "two_factor_required" in result && result.two_factor_required === true;
}
