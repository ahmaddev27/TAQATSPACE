import { getTranslations, getLocale } from "next-intl/server";
import { Icon } from "@/components/ui/Icon";
import type { Invoice } from "@/lib/api/invoices";
import { DownloadPdfLink } from "./DownloadPdfLink";
import { InvoiceStatusBadge } from "./InvoiceStatusBadge";
import { SubmitReceiptButton } from "./SubmitReceiptButton";
import { ReceiptViewerButton } from "./ReceiptViewerButton";
import { invoiceDate, invoiceMoney, sumAmounts } from "./format";

export interface MemberInvoicesListProps {
  invoices: Invoice[];
}

/**
 * Read-only invoice history for a freelancer. Server Component: it renders a
 * plain table (the only interactive bit, the PDF link, is its own component).
 */
export async function MemberInvoicesList({ invoices }: MemberInvoicesListProps) {
  const [t, locale] = await Promise.all([
    getTranslations("invoices.member"),
    getLocale(),
  ]);

  if (invoices.length === 0) {
    return (
      <div className="card card-pad empty-state" style={{ minHeight: 220 }}>
        <span className="st-ico">
          <Icon name="receipt" />
        </span>
        <div>
          <div className="h3">{t("empty.title")}</div>
          <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
            {t("empty.body")}
          </div>
        </div>
      </div>
    );
  }

  const totalPaid = sumAmounts(
    invoices.filter((inv) => inv.status === "paid").map((inv) => inv.amount),
  );
  const currency = invoices[0]?.currency ?? "₪";

  return (
    <div className="table-wrap">
      <div className="tbl-scroll">
        <table className="tbl">
          <thead>
            <tr>
              <th>{t("table.invoice")}</th>
              <th>{t("table.description")}</th>
              <th className="num">{t("table.due")}</th>
              <th className="num">{t("table.paidAt")}</th>
              <th className="num">{t("table.amount")}</th>
              <th>{t("table.status")}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {invoices.map((inv) => (
              <tr key={inv.id}>
                <td className="cell-num ltr" style={{ fontWeight: 600 }}>
                  {inv.invoice_number}
                </td>
                <td className="muted">{inv.notes ?? "—"}</td>
                <td className="cell-num ltr">{invoiceDate(inv.due_date)}</td>
                <td className="cell-num ltr">{invoiceDate(inv.paid_at)}</td>
                <td className="cell-num" style={{ fontWeight: 600 }}>
                  {invoiceMoney(inv.amount, inv.currency)}
                </td>
                <td>
                  <InvoiceStatusBadge status={inv.status} locale={locale} />
                  {inv.status === "payment_rejected" &&
                    inv.receipt_rejected_reason && (
                      <div
                        className="ltr"
                        style={{
                          marginTop: 4,
                          fontSize: "var(--fs-xs)",
                          color: "var(--danger)",
                          maxWidth: 220,
                          whiteSpace: "normal",
                        }}
                      >
                        {t("rejectedReason", {
                          reason: inv.receipt_rejected_reason,
                        })}
                      </div>
                    )}
                </td>
                <td>
                  <div className="row-actions">
                    {(inv.status === "pending" ||
                      inv.status === "overdue" ||
                      inv.status === "payment_rejected") && (
                      <SubmitReceiptButton
                        invoiceId={inv.id}
                        rejected={inv.status === "payment_rejected"}
                      />
                    )}
                    {inv.receipt_url && (
                      <ReceiptViewerButton
                        url={inv.receipt_url}
                        label={t("receipt.view")}
                        icon="receipt"
                      />
                    )}
                    <DownloadPdfLink
                      invoiceId={inv.id}
                      label={t("downloadPdf")}
                    />
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="table-foot">
        <span>{t("foot.count", { count: invoices.length })}</span>
        <span className="tnum">
          {t("foot.totalPaid")}: {invoiceMoney(totalPaid, currency)}
        </span>
      </div>
    </div>
  );
}
