"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "./client";
import type { Subscription } from "@/lib/types";

/**
 * Freelancer Server Actions (M06). Client components invoke these instead of
 * touching the Laravel API directly, so the bearer cookie never leaves the
 * server. Each action returns the shared `ActionResult` shape.
 *
 * Profile + password management now lives in the role-agnostic
 * `@/lib/actions/profile` module, shared with the admin profile page.
 */

/** Cancel an active subscription, then refresh the dashboard + subscription views. */
export async function cancelSubscriptionAction(
  subscriptionId: string,
): Promise<ActionResult<Subscription>> {
  const result = await authedMutate<Subscription>(
    `/member/subscriptions/${subscriptionId}/cancel`,
    { method: "PUT" },
  );

  if (result.ok) {
    revalidatePath("/freelancer/subscription");
    revalidatePath("/freelancer");
  }

  return result;
}
