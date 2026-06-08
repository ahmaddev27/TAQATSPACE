import { redirect } from "next/navigation";
import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, User } from "@/lib/types";
import type { AdminPermission } from "@/lib/types/auth";

/**
 * Server guard for an admin page. Redirects to the admin home (which is open to
 * every admin) when the authenticated admin lacks `permission`.
 *
 * Defense-in-depth: the API already gates each endpoint by the same permission
 * and the sidebar hides the link, but this stops a direct-URL visit from
 * rendering an empty/erroring page. Returns the fetched user so the caller can
 * reuse it (e.g. the current account's id) without a second `/auth/me` round-trip.
 */
export async function requireAdminPermission(
  locale: string,
  permission: AdminPermission,
): Promise<User> {
  const me = await serverFetch<ApiEnvelope<{ user: User }>>("/auth/me");
  const granted = me.data.user.permissions ?? [];

  if (!granted.includes(permission)) {
    redirect(`/${locale}/admin`);
  }

  return me.data.user;
}
