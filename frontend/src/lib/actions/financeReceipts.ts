"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { ChequeStatus, Receipt } from "@/lib/types/financeReceipts";

/**
 * Finance v2 receipt mutations. Each wraps `authedMutate` and revalidates the
 * receipts page (and the invoices, whose paid totals move) on success. Permission
 * and flag enforcement live on the backend (`finance_v2` flag, finance permissions).
 */

const BASE = "/workspace/finance/receipts";

function revalidate(): void {
  revalidatePath("/[locale]/(dashboard)/owner/finance/receipts", "page");
  revalidatePath("/[locale]/(dashboard)/owner/invoices", "page");
}

async function mutate(
  path: string,
  options: { method: "POST" | "PUT"; body?: unknown; formData?: FormData },
): Promise<ActionResult<Receipt>> {
  const result = await authedMutate<Receipt>(`${BASE}${path}`, options);
  if (result.ok) revalidate();
  return result;
}

/** Multipart: the optional proof file travels with the fields and allocations. */
export async function createReceipt(formData: FormData): Promise<ActionResult<Receipt>> {
  return mutate("", { method: "POST", formData });
}

export async function allocateReceipt(
  id: string,
  allocations: { invoice_id: string; amount: string }[],
): Promise<ActionResult<Receipt>> {
  return mutate(`/${id}/allocations`, { method: "POST", body: { allocations } });
}

export async function changeChequeStatus(
  id: string,
  status: ChequeStatus,
): Promise<ActionResult<Receipt>> {
  return mutate(`/${id}/cheque-status`, { method: "PUT", body: { status } });
}

export async function voidReceipt(id: string, reason: string): Promise<ActionResult<Receipt>> {
  return mutate(`/${id}/void`, { method: "POST", body: { reason } });
}
