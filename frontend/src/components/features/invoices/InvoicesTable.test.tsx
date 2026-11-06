import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { InvoicesTable } from "./InvoicesTable";
import type { Invoice } from "@/lib/api/invoices";

// The shared mock drops the values; this one keeps them so the amounts can be asserted.
vi.mock("next-intl", () => ({
  useTranslations: () => (key: string, values?: { amount?: string }) => (values?.amount ? `${key} ${values.amount}` : key),
  useLocale: () => "en",
}));

vi.mock("@/i18n/navigation", () => ({
  Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
}));

// Row actions are not under test and pull in server actions.
vi.mock("./RecordPaymentButton", () => ({ RecordPaymentButton: () => null }));
vi.mock("./ReceiptReviewActions", () => ({ ReceiptReviewActions: () => null }));
vi.mock("./RemindButton", () => ({ RemindButton: () => null }));
vi.mock("./DownloadPdfLink", () => ({ DownloadPdfLink: () => null }));
vi.mock("./PaymentsHistoryButton", () => ({ PaymentsHistoryButton: () => null }));
vi.mock("./DeleteInvoiceButton", () => ({ DeleteInvoiceButton: () => null }));
vi.mock("./CreditNoteButton", () => ({ CreditNoteButton: () => null }));
vi.mock("./VoidInvoiceButton", () => ({ VoidInvoiceButton: () => null }));
vi.mock("./IssueInvoiceModal", () => ({ IssueInvoiceModal: () => null }));
vi.mock("@/components/features/admin/ExportCsvLink", () => ({ ExportCsvLink: () => null }));

const invoice = (over: Partial<Invoice>): Invoice =>
  ({
    id: "i1",
    subscription_id: "s1",
    invoice_number: "INV-1",
    amount: "100.00",
    amount_paid: "100.00",
    remaining_amount: "0.00",
    amount_formatted: "100.00",
    currency: "₪",
    status: "paid",
    is_overdue: false,
    due_date: "2026-02-10",
    paid_at: null,
    invoice_pdf_path: null,
    receipt_path: null,
    receipt_url: null,
    receipt_rejected_reason: null,
    receipt_reviewed_at: null,
    notes: null,
    created_at: "2026-02-01T00:00:00Z",
    ...over,
  }) as Invoice;

const renderTable = (inv: Invoice) => render(<InvoicesTable invoices={[inv]} members={[]} />);

describe("InvoicesTable refund lines", () => {
  it("shows the open refund and the part moved to the wallet as two separate lines", () => {
    renderTable(invoice({ refund_due: "30.00", refund_open: "10.00", moved_to_wallet: "20.00" }));

    expect(screen.getByText("table.refundDue ₪10")).toBeInTheDocument();
    expect(screen.getByText("table.movedToWallet ₪20")).toBeInTheDocument();
    expect(screen.queryByText(/₪30/)).not.toBeInTheDocument();
  });

  it("never shows refund_due once everything is in the wallet", () => {
    renderTable(invoice({ refund_due: "20.00", refund_open: "0.00", moved_to_wallet: "20.00" }));

    expect(screen.queryByText(/table.refundDue/)).not.toBeInTheDocument();
    expect(screen.getByText("table.movedToWallet ₪20")).toBeInTheDocument();
  });

  it("shows neither line when nothing is owed back", () => {
    renderTable(invoice({ refund_due: null, refund_open: null, moved_to_wallet: "0.00" }));

    expect(screen.queryByText(/table.refundDue/)).not.toBeInTheDocument();
    expect(screen.queryByText(/table.movedToWallet/)).not.toBeInTheDocument();
  });
});
