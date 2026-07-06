import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import {
  isPosOrderOpen,
  type PosOrder,
  type PosOrdersParams,
  type PosOrdersResult,
  type PosProductsResult,
  type PosReport,
  type PosReportParams,
  type PosSummary,
} from "@/lib/types/pos";

/**
 * Server-only readers for the owner's POS module (catalogue, inventory, orders,
 * summary). Imports `serverFetch` (next/headers), so Server Components only.
 * Client components import the types from `@/lib/types/pos`.
 */

function toQuery(params: PosOrdersParams | PosReportParams): string {
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

/**
 * Open orders (new/preparing/ready) for the terminal queue. The backend filters
 * by a single status, so we pull the most recent orders and keep the open ones
 * — sufficient for the live queue, where open tickets are the recent ones.
 */
export async function ownerPosOpenOrders(): Promise<PosOrder[]> {
  const { orders } = await ownerPosOrders({ per_page: 50 });
  return orders.filter((o) => isPosOrderOpen(o.status));
}

/**
 * The latest orders in any status (paid/unpaid, open/closed) for the terminal's
 * read-only "recent orders" panel. Distinct from the open-orders queue, which
 * only surfaces actionable tickets.
 */
export async function ownerPosRecentOrders(limit = 8): Promise<PosOrder[]> {
  const { orders } = await ownerPosOrders({ per_page: limit });
  return orders;
}

/** Sales analytics for the owner's workspace over an optional date range. */
export async function ownerPosReport(
  params: PosReportParams = {},
): Promise<PosReport> {
  const res = await serverFetch<ApiEnvelope<PosReport>>(
    `/pos/reports${toQuery(params)}`,
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
export const cashierPosOpenOrders = ownerPosOpenOrders;
export const cashierPosRecentOrders = ownerPosRecentOrders;
export const cashierPosReport = ownerPosReport;
