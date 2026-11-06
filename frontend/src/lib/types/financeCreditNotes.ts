/**
 * Client-safe types for Finance v2 credit notes. Mirrors the Laravel
 * `CreditNoteResource` shape.
 */
import type { SimpleMeta } from "@/lib/types";

export interface CreditNote {
  id: string;
  number: string;
  invoice_id: string;
  invoice_number: string | null;
  customer: { id: string; name: string } | null;
  /** Decimal strings, e.g. "150.50". */
  amount: string;
  reason: string;
  issued_by: { id: string; name: string } | null;
  issued_at: string | null;
  voided_at: string | null;
  created_at: string | null;
}

export interface CreditNotesResult {
  items: CreditNote[];
  meta: SimpleMeta;
}

export interface CreditNoteListParams {
  invoice_id?: string;
  per_page?: number;
  page?: number;
}
