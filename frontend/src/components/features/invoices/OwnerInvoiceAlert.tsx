import { ownerInvoices } from "@/lib/api/invoices";
import { InvoiceAlert } from "./InvoiceAlert";

/**
 * Server wrapper for the owner dashboard banner. Fetches the workspace invoices
 * once, counts overdue/pending, and hands them to the client banner. Renders
 * nothing (and never throws) when the data can't be loaded.
 */
export async function OwnerInvoiceAlert() {
  let overdue = 0;
  let pending = 0;

  try {
    const { invoices } = await ownerInvoices({ per_page: 200 });
    overdue = invoices.filter((inv) => inv.status === "overdue").length;
    pending = invoices.filter((inv) => inv.status === "pending").length;
  } catch {
    return null;
  }

  if (overdue + pending === 0) return null;

  return (
    <InvoiceAlert
      variant="owner"
      overdue={overdue}
      pending={pending}
      href="/owner/invoices"
      dismissKey="owner"
    />
  );
}
