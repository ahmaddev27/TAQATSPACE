import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type { CashiersResult } from "@/lib/types/cashier";

/**
 * Server-only reader for the owner's café/cashier staff (accounts + open
 * invitations). Imports `serverFetch` (next/headers), so Server Components only.
 */
export async function ownerCashiers(): Promise<CashiersResult> {
  const res = await serverFetch<ApiEnvelope<CashiersResult>>("/workspace/cashiers");
  return res.data;
}
