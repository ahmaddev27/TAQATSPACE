/**
 * Client-safe types for Finance v2 receipts. Mirrors the Laravel
 * `ReceiptResource` shape and the receipt request rules.
 */
import type { SimpleMeta } from "@/lib/types";

export type ReceiptMethod = "cash" | "card" | "transfer" | "cheque";

export const RECEIPT_METHODS: ReceiptMethod[] = ["cash", "card", "transfer", "cheque"];

export type ChequeStatus = "received" | "deposited" | "cleared" | "bounced";

/** The moves a cheque may make from each status (mirrors the backend enum). */
export const CHEQUE_NEXT: Record<ChequeStatus, ChequeStatus[]> = {
  received: ["deposited", "bounced"],
  deposited: ["cleared", "bounced"],
  cleared: ["bounced"],
  bounced: [],
};

export type ReceiptSort = "received_at" | "collected_at";

export interface ReceiptAllocation {
  invoice_id: string;
  invoice_number: string | null;
  /** Decimal string. */
  amount: string;
}

export interface Receipt {
  id: string;
  number: string;
  customer_id: string | null;
  customer?: { id: string; name: string } | null;
  method: ReceiptMethod;
  /** Decimal strings, e.g. "150.50". */
  amount: string;
  allocated_amount: string;
  unallocated_amount: string;
  received_at: string | null;
  collected_at: string | null;
  reference: string | null;
  cheque_status: ChequeStatus | null;
  /** True once the money counts as collected (a cheque only after it clears). */
  is_collected: boolean;
  /** Short-lived signed link to the private proof file. */
  receipt_url: string | null;
  voided_at: string | null;
  void_reason: string | null;
  allocations?: ReceiptAllocation[];
  created_at: string | null;
}

export interface ReceiptsResult {
  items: Receipt[];
  meta: SimpleMeta;
}

export interface ReceiptListParams {
  sort?: ReceiptSort;
  direction?: "asc" | "desc";
  method?: ReceiptMethod;
  state?: "active" | "voided";
  per_page?: number;
  page?: number;
}

/** An issued, not fully paid invoice the owner can allocate a receipt to. */
export interface OpenInvoice {
  id: string;
  invoice_number: string;
  customer_id: string;
  /** Decimal string. */
  remaining_amount: string;
}

export interface ReceiptCustomer {
  id: string;
  name: string;
}
