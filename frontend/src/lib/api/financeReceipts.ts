import { serverFetch } from "@/lib/api";
import { ownerInvoices } from "@/lib/api/invoices";
import { listMembers } from "@/lib/api/members";
import type { ApiEnvelope } from "@/lib/types";
import type {
  OpenInvoice,
  ReceiptCustomer,
  ReceiptListParams,
  ReceiptsResult,
} from "@/lib/types/financeReceipts";

/**
 * Server-only readers for Finance v2 receipts. Import `serverFetch`
 * (next/headers), so Server Components only.
 */

/** Receipts of the workspace, sorted by receipt date or collection date. */
export async function ownerReceipts(params: ReceiptListParams = {}): Promise<ReceiptsResult> {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();

  const res = await serverFetch<ApiEnvelope<ReceiptsResult>>(
    `/workspace/finance/receipts${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}

/** Customers and their open invoices, for the allocation form. */
export async function receiptFormData(): Promise<{
  customers: ReceiptCustomer[];
  invoices: OpenInvoice[];
}> {
  const [members, invoices] = await Promise.all([
    listMembers({ status: "all", per_page: 100 }),
    ownerInvoices({ per_page: 100 }),
  ]);

  return {
    customers: members.members.map((m) => ({ id: m.user.id, name: m.user.name })),
    invoices: invoices.invoices
      .filter((i) => i.doc_status === "issued" && i.payment_status !== "paid" && i.customer_id)
      .map((i) => ({
        id: i.id,
        invoice_number: i.invoice_number,
        customer_id: i.customer_id as string,
        remaining_amount: i.remaining_amount,
      })),
  };
}
