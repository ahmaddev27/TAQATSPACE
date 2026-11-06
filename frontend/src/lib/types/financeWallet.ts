/**
 * Client-safe types for the Finance v2 customer wallet and payment vouchers.
 * Mirrors the Laravel wallet endpoints and `PaymentVoucherResource`.
 */
import type { SimpleMeta } from "@/lib/types";

/** Ways money can be handed over by hand; nothing here charges a card. */
export const WALLET_METHODS = ["cash", "transfer", "cheque", "card"] as const;
export type WalletMethod = (typeof WALLET_METHODS)[number];

export interface WalletRow {
  customer: { id: string; name: string };
  /** Decimal string, e.g. "150.50". */
  balance: string;
  last_entry_at: string | null;
}

export interface WalletsResult {
  currency: string | null;
  items: WalletRow[];
  meta: SimpleMeta;
}

export interface PaymentVoucher {
  id: string;
  number: string;
  customer_id: string;
  customer: { id: string; name: string } | null;
  method: WalletMethod;
  amount: string;
  reason: string | null;
  reference: string | null;
  wallet_entry_id: string | null;
  paid_at: string | null;
  voided_at: string | null;
  void_reason: string | null;
  created_at: string | null;
}

export interface PaymentVouchersResult {
  items: PaymentVoucher[];
  meta: SimpleMeta;
}

export type WalletAction = "deposit" | "return" | "settle" | "forfeit";
