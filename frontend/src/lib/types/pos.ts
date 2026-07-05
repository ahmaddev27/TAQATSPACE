/**
 * Client-safe types for the POS (café) module — catalogue, inventory, orders,
 * and the at-a-glance summary. Mirrors the Laravel `Pos*Resource` shapes and the
 * store/adjust request rules. Kept separate from `lib/api/pos.ts` (which imports
 * the server-only `serverFetch`) so client components never pull server code.
 */
import type { SimpleMeta } from "@/lib/types";

/** Inventory-movement kinds an owner/cashier can apply manually. `sale` is system-only. */
export type StockMovementType = "restock" | "adjustment";

/** How an order was rung up. */
export type PosOrderSource = "cashier" | "freelancer";

/** Lifecycle of a POS order. */
export type PosOrderStatus = "pending" | "paid" | "cancelled";

/** Products with a `track_stock` count at or below this are considered low. */
export const POS_LOW_STOCK_THRESHOLD = 5;

/* --------------------------------- Products ---------------------------------- */

export interface PosProduct {
  id: string;
  name: string;
  category: string | null;
  sku: string | null;
  /** Decimal string, e.g. "12.00". */
  price: string;
  track_stock: boolean;
  stock_qty: number;
  is_active: boolean;
  /** Derived: active AND (untracked OR in stock). */
  is_sellable: boolean;
  created_at: string | null;
}

export interface PosProductsResult {
  products: PosProduct[];
  meta: SimpleMeta;
}

/** Body for `POST /pos/products` and (partial) `PUT /pos/products/{id}`. */
export interface PosProductInput {
  name: string;
  category?: string | null;
  sku?: string | null;
  price: number;
  track_stock?: boolean;
  stock_qty?: number;
  is_active?: boolean;
}

/** Body for `POST /pos/products/{id}/stock`. */
export interface AdjustStockInput {
  type: StockMovementType;
  /** Signed delta: positive adds, negative removes. */
  qty_change: number;
  note?: string | null;
}

/* ---------------------------------- Orders ----------------------------------- */

export interface PosOrderItem {
  id: string;
  product_id: string;
  name: string;
  /** Decimal string. */
  unit_price: string;
  qty: number;
  /** Decimal string. */
  line_total: string;
}

export interface PosOrderMember {
  id: string;
  name: string;
}

export interface PosOrder {
  id: string;
  order_number: string;
  source: PosOrderSource;
  status: PosOrderStatus;
  customer_name: string | null;
  member?: PosOrderMember | null;
  /** Decimal strings. */
  subtotal: string;
  discount: string;
  total: string;
  paid_at: string | null;
  items?: PosOrderItem[];
  created_at: string | null;
}

export interface PosOrdersResult {
  orders: PosOrder[];
  meta: SimpleMeta;
}

export interface PosOrdersParams {
  status?: PosOrderStatus;
  per_page?: number;
  page?: number;
}

/* --------------------------------- Summary ----------------------------------- */

export interface PosSummary {
  /** Decimal string of today's paid-order revenue. */
  today_sales: string;
  today_orders: number;
  pending_orders: number;
  low_stock: number;
}
