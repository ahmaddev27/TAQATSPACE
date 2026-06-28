"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Modal } from "@/components/ui/Modal";
import type { InvoicePaymentRow } from "@/lib/api/invoices";
import { ReceiptViewerButton } from "./ReceiptViewerButton";

export interface PaymentsHistoryButtonProps {
  invoiceNumber: string;
  payments: InvoicePaymentRow[];
  currency: string;
}

/** Owner: view the per-installment payment history recorded against an invoice. */
export function PaymentsHistoryButton({
  invoiceNumber,
  payments,
  currency,
}: PaymentsHistoryButtonProps) {
  const t = useTranslations("invoices.owner.history");
  const [open, setOpen] = useState(false);

  if (payments.length === 0) return null;

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        icon="receipt"
        onClick={() => setOpen(true)}
      >
        {t("action", { count: payments.length })}
      </Button>

      {open && (
        <Modal
          title={t("title", { invoice: invoiceNumber })}
          icon="receipt"
          onClose={() => setOpen(false)}
          footer={
            <Button variant="ghost" onClick={() => setOpen(false)}>
              {t("close")}
            </Button>
          }
        >
          <div className="table-wrap">
            <table className="tbl">
              <thead>
                <tr>
                  <th>{t("date")}</th>
                  <th>{t("amount")}</th>
                  <th>{t("receipt")}</th>
                </tr>
              </thead>
              <tbody>
                {payments.map((p) => (
                  <tr key={p.id}>
                    <td className="cell-num ltr">
                      {p.paid_at ? p.paid_at.slice(0, 10) : "—"}
                    </td>
                    <td className="cell-num" style={{ fontWeight: 600 }}>
                      {currency} {p.amount}
                    </td>
                    <td>
                      {p.receipt_url ? (
                        <ReceiptViewerButton
                          url={p.receipt_url}
                          label={t("view")}
                          icon="receipt"
                        />
                      ) : (
                        <span className="muted-3">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Modal>
      )}
    </>
  );
}
