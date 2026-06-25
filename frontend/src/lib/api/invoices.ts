import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, InvoiceStatus, SimpleMeta } from "@/lib/types";

/* -------------------------------------------------------------------------- */
/*  Types (owned by the invoicing area — mirrors App\Http\Resources\Invoice*)  */
/* -------------------------------------------------------------------------- */

/** Member info nested on an owner invoice (via subscription.member). */
export interface InvoiceMember {
  id: string;
  name: string;
  email: string;
  phone: string | null;
}

/** Workspace info nested on an invoice (via subscription.workspace). */
export interface InvoiceWorkspace {
  id: string;
  name: string;
  city: string;
}

/** Seat info nested on an invoice (via subscription.seat). */
export interface InvoiceSeat {
  id: string;
  seat_number: string;
}

/** The subscription chain eager-loaded onto an invoice. */
export interface InvoiceSubscription {
  id: string;
  plan_type: string;
  /** Decimal string, e.g. "25.00". */
  monthly_price: string;
  start_date: string | null;
  end_date: string | null;
  status: string;
  workspace: InvoiceWorkspace | null;
  member: InvoiceMember | null;
  seat: InvoiceSeat | null;
}

/** A single invoice as returned by `InvoiceResource`. */
export interface Invoice {
  id: string;
  subscription_id: string;
  invoice_number: string;
  amount: string;
  /** Running total paid + remaining balance (decimal strings) for partial payments. */
  amount_paid: string;
  remaining_amount: string;
  amount_formatted: string;
  currency: string;
  status: InvoiceStatus;
  due_date: string | null;
  paid_at: string | null;
  is_overdue: boolean;
  invoice_pdf_path: string | null;
  /** Stored payment-receipt path + a resolved (presigned) URL, when uploaded. */
  receipt_path: string | null;
  receipt_url: string | null;
  /** Owner's reason when a submitted receipt was rejected (status payment_rejected). */
  receipt_rejected_reason: string | null;
  receipt_reviewed_at: string | null;
  notes: string | null;
  created_at: string | null;
  subscription?: InvoiceSubscription;
}

/** Owner summary tiles. Amounts are decimal strings (e.g. "1500.00"). */
export interface InvoiceSummary {
  total_outstanding: string;
  total_overdue: string;
  paid_this_month: string;
}

/** Owner listing payload: `{ invoices, summary, meta }`. */
export interface OwnerInvoicesResult {
  invoices: Invoice[];
  summary: InvoiceSummary;
  meta: SimpleMeta;
}

/** Member listing payload: `{ invoices, meta }`. */
export interface MemberInvoicesResult {
  invoices: Invoice[];
  meta: SimpleMeta;
}

export interface InvoiceListParams {
  status?: InvoiceStatus;
  /** Filter by issue month in `YYYY-MM` form. */
  month?: string;
  per_page?: number;
  page?: number;
}

/* -------------------------------------------------------------------------- */
/*  Queries                                                                    */
/* -------------------------------------------------------------------------- */

function toQuery(params: InvoiceListParams): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/** Owner: invoices for THIS workspace, with summary tiles. */
export async function ownerInvoices(
  params: InvoiceListParams = {},
): Promise<OwnerInvoicesResult> {
  const res = await serverFetch<ApiEnvelope<OwnerInvoicesResult>>(
    `/workspace/invoices${toQuery(params)}`,
  );
  return res.data;
}

/** Freelancer: the authenticated member's own invoice history. */
export async function memberInvoices(
  params: InvoiceListParams = {},
): Promise<MemberInvoicesResult> {
  const res = await serverFetch<ApiEnvelope<MemberInvoicesResult>>(
    `/member/invoices${toQuery(params)}`,
  );
  return res.data;
}
