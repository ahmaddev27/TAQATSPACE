import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type { PaymentVouchersResult, WalletsResult } from "@/lib/types/financeWallet";

/**
 * Server-only readers for the Finance v2 wallet and payment vouchers. Import
 * `serverFetch` (next/headers), so Server Components only.
 */

/** Customers with a wallet and their balances. */
export async function ownerWallets(perPage = 100): Promise<WalletsResult> {
  const res = await serverFetch<ApiEnvelope<WalletsResult>>(`/workspace/finance/wallet?per_page=${perPage}`);
  return res.data;
}

/** Payment vouchers of the workspace, newest first. */
export async function ownerPaymentVouchers(perPage = 100): Promise<PaymentVouchersResult> {
  const res = await serverFetch<ApiEnvelope<PaymentVouchersResult>>(
    `/workspace/finance/payment-vouchers?per_page=${perPage}`,
  );
  return res.data;
}
