import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type { CreditNoteListParams, CreditNotesResult } from "@/lib/types/financeCreditNotes";

/**
 * Server-only reader for Finance v2 credit notes. Imports `serverFetch`
 * (next/headers), so Server Components only.
 */

/** Credit notes of the workspace, newest first. */
export async function ownerCreditNotes(params: CreditNoteListParams = {}): Promise<CreditNotesResult> {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();

  const res = await serverFetch<ApiEnvelope<CreditNotesResult>>(
    `/workspace/finance/credit-notes${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}
