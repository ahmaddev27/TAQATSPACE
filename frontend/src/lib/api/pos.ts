import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type {
  PosOrdersParams,
  PosOrdersResult,
  PosProductsResult,
  PosSummary,
} from "@/lib/types/pos";

/**
 * Server-only readers for the owner's POS module (catalogue, inventory, orders,
 * summary). Imports `serverFetch` (next/headers), so Server Components only.
 * Client components import the types from `@/lib/types/pos`.
 */

function toQuery(params: PosOrdersParams): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/** POS catalogue for the owner's workspace (products + inventory levels). */
export async function ownerPosProducts(): Promise<PosProductsResult> {
  const res = await serverFetch<ApiEnvelope<PosProductsResult>>("/pos/products");
  return res.data;
}

/** At-a-glance POS figures (today's sales/orders, pending orders, low stock). */
export async function ownerPosSummary(): Promise<PosSummary> {
  const res = await serverFetch<ApiEnvelope<PosSummary>>("/pos/summary");
  return res.data;
}

/** Recent POS orders, optionally filtered by status. */
export async function ownerPosOrders(
  params: PosOrdersParams = {},
): Promise<PosOrdersResult> {
  const res = await serverFetch<ApiEnvelope<PosOrdersResult>>(
    `/pos/orders${toQuery(params)}`,
  );
  return res.data;
}

/* ------------------------------ Cashier context ------------------------------ */
/**
 * The `/pos/*` endpoints resolve the workspace from the authenticated account,
 * so a cashier hits the exact same routes as the owner. These thin aliases give
 * the cashier terminal an intent-revealing name without duplicating fetch logic.
 */

export const cashierPosProducts = ownerPosProducts;
export const cashierPosSummary = ownerPosSummary;
export const cashierPosOrders = ownerPosOrders;
