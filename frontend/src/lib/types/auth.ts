export type UserRole = "freelancer" | "workspace_owner" | "admin";

export type UserStatus = "active" | "suspended" | "pending_verification";

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  status: UserStatus;
  specialty: string | null;
  bio: string | null;
  avatar: string | null;
  email_verified_at: string | null;
  created_at: string;
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
