import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, MemberSummary } from "@/lib/types";

/** Freelancer dashboard summary (subscription, seat, next invoice, counters). */
export async function getMemberSummary(): Promise<MemberSummary> {
  const res = await serverFetch<ApiEnvelope<MemberSummary>>(
    "/member/dashboard",
  );
  return res.data;
}
