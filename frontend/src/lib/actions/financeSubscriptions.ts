"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { SubscriptionTermsInput } from "@/lib/types/financePlans";

/**
 * Finance v2: set the financial terms of a subscription. The backend enforces
 * the `finance_v2` flag, ownership and the plan/hours/seat compatibility rules.
 */
export async function updateSubscriptionTerms(
  subscriptionId: string,
  input: SubscriptionTermsInput,
): Promise<ActionResult> {
  const result = await authedMutate(`/workspace/subscriptions/${subscriptionId}/financial`, {
    method: "PUT",
    body: input,
  });
  if (result.ok) revalidatePath("/[locale]/(dashboard)/owner/subscriptions", "page");
  return result;
}
