export type UserRole = "freelancer" | "workspace_owner" | "admin";

export type UserStatus = "active" | "suspended" | "pending_verification";

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
