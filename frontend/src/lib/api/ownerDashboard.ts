import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, OwnerStats } from "@/lib/types";

/**
 * Owner dashboard aggregate. Returns a zeroed payload (still 200) for owners
 * who have not created a workspace yet, so callers can render unconditionally.
 */
export async function getOwnerStats(): Promise<OwnerStats> {
  const res = await serverFetch<ApiEnvelope<OwnerStats>>(
    "/workspace/dashboard",
  );
  return res.data;
}
