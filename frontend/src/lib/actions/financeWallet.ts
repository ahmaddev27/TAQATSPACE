"use server";

import { revalidatePath } from "next/cache";
import { ApiError, serverFetch } from "@/lib/api";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { DepositMethod, WalletDetail, WalletMethod } from "@/lib/types/financeWallet";

/**
 * Finance v2 wallet mutations. Returning money, forfeiting and voiding a voucher
 * are sensitive: a fresh two-factor code is verified first when one is supplied,
 * and the backend answers 403 while the step-up is missing. Flag and permission
 * enforcement live on the backend.
 */

const BASE = "/workspace/finance";

function revalidate(): void {
  revalidatePath("/[locale]/(dashboard)/owner/finance/wallet", "page");
  revalidatePath("/[locale]/(dashboard)/owner/invoices", "page");
}

async function send(path: string, body: unknown, code?: string): Promise<ActionResult<unknown>> {
  if (code) {
    const verified = await authedMutate("/auth/two-factor/verify", { body: { code } });
    if (!verified.ok) return verified;
  }

  const result = await authedMutate<unknown>(`${BASE}${path}`, { body });
  if (result.ok) revalidate();
  return result;
}

export async function receiveDeposit(
  customerId: string,
  input: { amount: string; method: DepositMethod; reason?: string; request_key: string },
): Promise<ActionResult<unknown>> {
  return send(`/wallet/${customerId}/deposits`, input);
}

export async function returnDeposit(
  customerId: string,
  input: { amount: string; method: WalletMethod; reason: string; reference?: string; request_key: string },
  twoFactorCode?: string,
): Promise<ActionResult<unknown>> {
  return send(`/wallet/${customerId}/returns`, input, twoFactorCode);
}

export async function settleWallet(
  customerId: string,
  input: { invoice_id: string; amount: string; request_key: string },
): Promise<ActionResult<unknown>> {
  return send(`/wallet/${customerId}/settlements`, input);
}

export async function forfeitWallet(
  customerId: string,
  input: { amount: string; reason: string; request_key: string },
  twoFactorCode?: string,
): Promise<ActionResult<unknown>> {
  return send(`/wallet/${customerId}/forfeits`, input, twoFactorCode);
}

/** Reverses a wrong deposit or forfeit; posts a new entry, the original is never edited. */
export async function reverseWalletEntry(
  customerId: string,
  entryId: string,
  input: { reason: string; request_key: string },
  twoFactorCode?: string,
): Promise<ActionResult<unknown>> {
  return send(`/wallet/${customerId}/entries/${entryId}/reverse`, input, twoFactorCode);
}

/** One customer's wallet with its ledger entries, for the entries modal. */
export async function loadWalletEntries(customerId: string): Promise<ActionResult<WalletDetail>> {
  try {
    const res = await serverFetch<{ data: WalletDetail }>(`${BASE}/wallet/${customerId}?per_page=50`);
    return { ok: true, data: res.data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { ok: false, status: error.status, message: error.message };
    }
    return { ok: false, status: 500, message: "Request failed." };
  }
}

export async function voidPaymentVoucher(
  id: string,
  reason: string,
  twoFactorCode?: string,
): Promise<ActionResult<unknown>> {
  return send(`/payment-vouchers/${id}/void`, { reason }, twoFactorCode);
}
