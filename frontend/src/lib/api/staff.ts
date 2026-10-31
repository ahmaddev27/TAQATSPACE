import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type { StaffResult } from "@/lib/types/staff";

/**
 * Server-only reader for the owner's workspace staff (accounts + open
 * invitations). Imports `serverFetch` (next/headers), so Server Components only.
 */
export async function ownerStaff(): Promise<StaffResult> {
  const res = await serverFetch<ApiEnvelope<StaffResult>>("/workspace/staff");
  return res.data;
}
