"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { Invoice } from "@/lib/api/invoices";

/* -------------------------------------------------------------------------- */
/*  Revalidation                                                              */
/* -------------------------------------------------------------------------- */

function revalidateInvoices(): void {
  revalidatePath("/[locale]/(dashboard)/owner/invoices", "page");
  revalidatePath("/[locale]/(dashboard)/freelancer/invoices", "page");
  // The dashboard alert banners read invoice counts too.
  revalidatePath("/[locale]/(dashboard)/owner", "page");
  revalidatePath("/[locale]/(dashboard)/freelancer", "page");
}

/* -------------------------------------------------------------------------- */
/*  Owner mutations                                                           */
/* -------------------------------------------------------------------------- */

export interface IssueInvoiceInput {
  member_id: string;
  amount: number;
  due_date: string;
  notes?: string | null;
}

/** Owner: issue a manual invoice for one of their members. */
export async function issueInvoice(
  input: IssueInvoiceInput,
): Promise<ActionResult<Invoice>> {
  const body: Record<string, unknown> = {
    member_id: input.member_id,
    amount: input.amount,
    due_date: input.due_date,
  };
  if (input.notes != null && input.notes !== "") {
    body.notes = input.notes;
  }

  const result = await authedMutate<Invoice>("/workspace/invoices", {
    method: "POST",
    body,
  });
  if (result.ok) revalidateInvoices();
  return result;
}

/** Owner: record a manual payment. `paidAt` is an optional ISO date. */
export async function markPaid(
  invoiceId: string,
  paidAt?: string | null,
): Promise<ActionResult<Invoice>> {
  const body = paidAt != null && paidAt !== "" ? { paid_at: paidAt } : undefined;

  const result = await authedMutate<Invoice>(
    `/workspace/invoices/${invoiceId}/pay`,
    { method: "PUT", body },
  );
  if (result.ok) revalidateInvoices();
  return result;
}

/**
 * Owner: send a payment reminder. The backend rate-limits to one per 24h and
 * answers 429 when called too soon — surfaced to the caller via `ActionResult`.
 */
export async function sendReminder(
  invoiceId: string,
): Promise<ActionResult> {
  return authedMutate(`/workspace/invoices/${invoiceId}/remind`, {
    method: "POST",
  });
}
