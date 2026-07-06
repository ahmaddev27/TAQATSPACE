"use server";

import { cookies } from "next/headers";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import { ROLE_COOKIE, TOKEN_MAX_AGE } from "@/lib/auth";
import type { User, UserRole } from "@/lib/types/auth";

/** Shape returned by the accept endpoint on success. */
export interface AcceptCashierInvitationResult {
  user: User;
  role: UserRole;
}

/**
 * Accept a pending cashier invitation surfaced during SSO onboarding. On success
 * the account becomes `role=cashier`, is attached to the workspace, and onboarding
 * completes. The client refreshes the session and routes to the cashier dashboard,
 * so no revalidation is performed here.
 */
export async function acceptCashierInvitation(
  invitationId: string,
): Promise<ActionResult<AcceptCashierInvitationResult>> {
  const result = await authedMutate<AcceptCashierInvitationResult>(
    `/cashier/invitations/${invitationId}/accept`,
    { method: "POST" },
  );

  // The account's role changed to `cashier`; refresh the middleware-readable
  // role cookie so it routes to /cashier instead of bouncing to the stale
  // (freelancer placeholder) dashboard.
  if (result.ok) {
    const store = await cookies();
    store.set(ROLE_COOKIE, result.data.role, {
      httpOnly: false,
      sameSite: "lax",
      secure: process.env.NODE_ENV === "production",
      path: "/",
      maxAge: TOKEN_MAX_AGE,
    });
  }

  return result;
}

/**
 * Decline a pending cashier invitation. The invitation is marked declined; the
 * user then proceeds to the normal freelancer/workspace-owner role choice.
 */
export async function declineCashierInvitation(
  invitationId: string,
): Promise<ActionResult> {
  return authedMutate(`/cashier/invitations/${invitationId}/decline`, {
    method: "POST",
  });
}
