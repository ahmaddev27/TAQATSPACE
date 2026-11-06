/**
 * Client-safe types for the Finance v2 customer wallet and payment vouchers.
 * Mirrors the Laravel wallet endpoints and `PaymentVoucherResource`.
 */
import type { SimpleMeta } from "@/lib/types";

/** Ways money can be handed over by hand; nothing here charges a card. */
export const WALLET_METHODS = ["cash", "transfer", "cheque", "card"] as const;
export type WalletMethod = (typeof WALLET_METHODS)[number];

/**
 * A deposit never takes a cheque: it could be paid out before it clears. The
 * backend refuses cheque and wallet with 422; the form simply does not offer them.
 */
export const DEPOSIT_METHODS = ["cash", "transfer", "card"] as const;
export type DepositMethod = (typeof DEPOSIT_METHODS)[number];

export interface WalletRow {
  customer: { id: string; name: string };
  /** Decimal string, e.g. "150.50". */
  balance: string;
  /** ISO-8601 UTC, e.g. "2026-11-06T10:00:00+00:00", or null. Format it with lib/dates. */
  last_entry_at: string | null;
}

export type WalletEntryType = "deposit_in" | "deposit_return" | "settlement" | "forfeit" | "credit" | "debit";

export interface WalletEntry {
  id: string;
  customer_id: string;
  type: WalletEntryType;
  /** Signed decimal string; negative takes money from the balance. */
  amount: string;
  method: string | null;
  reason: string | null;
  /** "invoice": owed-back money moved to (credit) or taken from (debit) the wallet; "wallet_entry": a reversal. */
  related_type: "invoice" | "receipt" | "payment_voucher" | "wallet_entry" | null;
  related_id: string | null;
  created_by: { id: string; name: string } | null;
  created_at: string | null;
  reversible: boolean;
  reversed: boolean;
}

export interface WalletDetail {
  currency: string | null;
  customer: { id: string; name: string };
  balance: string;
  items: WalletEntry[];
  meta: SimpleMeta;
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
