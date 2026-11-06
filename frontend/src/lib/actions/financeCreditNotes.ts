"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { CreditNote } from "@/lib/types/financeCreditNotes";

/**
 * Finance v2: issue a credit note on an issued invoice. It is a sensitive step,
 * so a fresh two-factor code is verified first when one is supplied; the backend
 * answers 403 while the step-up is still missing. Flag and permission
 * enforcement live on the backend.
 */
export async function issueCreditNote(
  invoiceId: string,
  amount: string,
  reason: string,
  twoFactorCode?: string,
  requestKey?: string,
): Promise<ActionResult<CreditNote>> {
  if (twoFactorCode) {
    const verified = await authedMutate("/auth/two-factor/verify", { body: { code: twoFactorCode } });
    if (!verified.ok) return verified;
  }

  const result = await authedMutate<CreditNote>(`/workspace/invoices/${invoiceId}/credit-notes`, {
    body: { amount, reason, ...(requestKey ? { request_key: requestKey } : {}) },
  });
  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/owner/invoices", "page");
    revalidatePath("/[locale]/(dashboard)/owner/finance/credit-notes", "page");
  }
  return result;
}
