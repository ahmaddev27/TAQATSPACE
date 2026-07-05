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
  | "view_reports";

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
  email_verified_at: string | null;
  created_at: string;
  /** Present for admin accounts only: holds the elevated super-admin role. */
  is_super_admin?: boolean;
  /** Present for admin accounts only: the account's effective permissions. */
  permissions?: AdminPermission[];
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

/** What our Next route handlers expose to the client (never the token). */
export interface ClientAuthResult {
  user: User;
  role: UserRole;
}
