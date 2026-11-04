import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type { PlansResult } from "@/lib/types/financePlans";

/**
 * Server-only reader for Finance v2 plans. Imports `serverFetch`
 * (next/headers), so Server Components only.
 */

/** Active plans of the workspace, ordered by code. */
export async function ownerPlans(): Promise<PlansResult> {
  const res = await serverFetch<ApiEnvelope<PlansResult>>(
    "/workspace/finance/plans?per_page=200",
  );
  return res.data;
}
