import { memberInvoices } from "@/lib/api/invoices";
import { InvoiceAlert } from "./InvoiceAlert";

/**
 * Server wrapper for the freelancer dashboard banner. Counts the member's
 * overdue/pending invoices and renders the dismissible client banner. Returns
 * nothing (and never throws) when there's nothing to show.
 */
export async function MemberInvoiceAlert() {
  let overdue = 0;
  let pending = 0;

  try {
    const { invoices } = await memberInvoices({ per_page: 200 });
    overdue = invoices.filter((inv) => inv.status === "overdue").length;
    pending = invoices.filter((inv) => inv.status === "pending").length;
  } catch {
    return null;
  }

  if (overdue + pending === 0) return null;

  return (
    <InvoiceAlert
      variant="member"
      overdue={overdue}
      pending={pending}
      href="/freelancer/invoices"
      dismissKey="member"
    />
  );
}
