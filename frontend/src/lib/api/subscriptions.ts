import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, Subscription } from "@/lib/types";

/** Freelancer: list own subscriptions (bare collection, not paginated). */
export async function listSubscriptions(): Promise<Subscription[]> {
  const res = await serverFetch<ApiEnvelope<Subscription[]>>(
    "/member/subscriptions",
  );
  return res.data;
}

/** Freelancer: subscription detail (workspace + seat + invoices loaded). */
export async function showSubscription(id: string): Promise<Subscription> {
  const res = await serverFetch<ApiEnvelope<Subscription>>(
    `/member/subscriptions/${id}`,
  );
  return res.data;
}

/** Freelancer: cancel a subscription. */
export async function cancelSubscription(id: string): Promise<Subscription> {
  const res = await serverFetch<ApiEnvelope<Subscription>>(
    `/member/subscriptions/${id}/cancel`,
    { method: "PUT" },
  );
  return res.data;
}
