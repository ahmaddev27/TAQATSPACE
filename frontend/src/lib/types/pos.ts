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

/** Tender used to settle a POS order. */
export type PosPaymentMethod = "cash" | "transfer";

/**
 * Fulfillment lifecycle of a POS order (independent of payment, which is
 * tracked by `paid_at`). Open = new/preparing/ready; terminal = completed,
 * cancelled, refunded.
 */
export type PosOrderStatus =
  | "new"
  | "preparing"
  | "ready"
  | "completed"
  | "cancelled"
  | "refunded";

/** Fulfillment statuses an order can be advanced TO via the status endpoint. */
export const POS_ADVANCEABLE_STATUSES: PosOrderStatus[] = [
  "preparing",
  "ready",
  "completed",
];

/** Statuses where an order is still open (in the active queue). */
export const POS_OPEN_STATUSES: PosOrderStatus[] = ["new", "preparing", "ready"];

/** True when an order is still open (awaiting fulfillment). */
export function isPosOrderOpen(status: PosOrderStatus): boolean {
  return POS_OPEN_STATUSES.includes(status);
}

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
  note: string | null;
  member?: PosOrderMember | null;
  /** Decimal strings. */
  subtotal: string;
  discount: string;
  total: string;
  /** Payment flag: ISO8601 when settled, null while unpaid. Independent of `status`. */
  paid_at: string | null;
  /** ISO8601 when the order was refunded, null otherwise. */
  refunded_at?: string | null;
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

/** A single cart line submitted when ringing up an order. */
export interface PosOrderLineInput {
  product_id: string;
  qty: number;
}

/** Body for `POST /pos/orders` (creates a pending order). */
export interface CreatePosOrderInput {
  items: PosOrderLineInput[];
  discount?: number;
  customer_name?: string | null;
  note?: string | null;
}

/** Body for `POST /pos/orders/{id}/pay`. */
export interface PayPosOrderInput {
  method: PosPaymentMethod;
}

/** A fulfillment status an order can be advanced to. */
export type PosAdvanceStatus = "preparing" | "ready" | "completed";

/** Body for `POST /pos/orders/{id}/status`. */
export interface UpdatePosOrderStatusInput {
  status: PosAdvanceStatus;
}

/**
 * Body for `POST /freelancer/pos/orders`. A freelancer rings up against a
 * workspace they are actively subscribed to; the order lands PENDING in the
 * cashier's queue and is settled at the counter (no discount/customer here).
 */
export interface FreelancerCreateOrderInput {
  workspace_id: string;
  items: PosOrderLineInput[];
  note?: string | null;
}

/* --------------------------------- Summary ----------------------------------- */

export interface PosSummary {
  /** Decimal string of today's paid-order revenue. */
  today_sales: string;
  today_orders: number;
  /** Open orders (new/preparing/ready) awaiting fulfillment. */
  pending_orders: number;
  low_stock: number;
}

/* ---------------------------------- Reports ---------------------------------- */

/** Inclusive date window (YYYY-MM-DD) a report covers. */
export interface PosReportRange {
  from: string;
  to: string;
}

/** Optional range filter for `GET /pos/reports`; both default server-side. */
export interface PosReportParams {
  from?: string;
  to?: string;
}

/**
 * Sales analytics for a workspace's POS over a date range. All money values are
 * fixed 2-decimal strings; counts/qty are integers. Covers PAID, non-refunded
 * orders only; refunds are reported separately.
 */
export interface PosReport {
  range: PosReportRange;
  totals: {
    /** Decimal string. */
    sales: string;
    orders: number;
    /** Decimal string. */
    avg: string;
  };
  top_products: Array<{
    name: string;
    qty: number;
    /** Decimal string. */
    total: string;
  }>;
  by_cashier: Array<{
    /** Cashier name, or "—" for unattributed (walk-in) rows. */
    name: string;
    orders: number;
    /** Decimal string. */
    total: string;
  }>;
  by_method: Array<{
    method: PosPaymentMethod;
    /** Decimal string. */
    total: string;
  }>;
  refunds: {
    count: number;
    /** Decimal string. */
    total: string;
  };
}
