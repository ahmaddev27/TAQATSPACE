"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { FileDropzone } from "@/components/ui/FileDropzone";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { useToast } from "@/components/providers/ToastProvider";
import { uploadInvoiceReceipt } from "@/lib/actions/admin";
import type { AdminInvoice } from "@/lib/api/admin";

const ACCEPT = "application/pdf,image/png,image/jpeg,image/jpg";

export interface ReceiptUploadModalProps {
  invoice: AdminInvoice;
  onClose: () => void;
}

/**
 * Upload a payment receipt for an invoice. The backend stores the file AND marks
 * the invoice paid in the same call, so this modal doubles as a "mark paid with
 * proof" flow; an optional paid date backdates the payment.
 */
export function ReceiptUploadModal({ invoice, onClose }: ReceiptUploadModalProps) {
  const t = useTranslations("admin.invoices");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [file, setFile] = useState<File | null>(null);
  const [paidAt, setPaidAt] = useState("");
  const [error, setError] = useState<string | null>(null);

  const submit = () => {
    if (!file) {
      setError(t("receiptModal.fileRequired"));
      return;
    }
    setError(null);
    startTransition(async () => {
      const res = await uploadInvoiceReceipt(invoice.id, file, paidAt || null);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.receiptUploaded") });
        onClose();
      } else {
        toast({
          tone: "err",
          title: t("toast.receiptFailed"),
          body: res.message,
        });
      }
    });
  };

  return (
    <Modal
      title={t("receiptModal.title")}
      icon="upload"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t("receiptModal.cancel")}
          </Button>
          <Button
            variant="primary"
            icon="check"
            loading={pending}
            disabled={!file}
            onClick={submit}
          >
            {t("receiptModal.submit")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 16 }}>
        <p className="muted">
          {t("receiptModal.body", { invoice: invoice.invoice_number })}
        </p>

        <Field label={t("receiptModal.file")} error={error ?? undefined}>
          <FileDropzone
            value={file}
            onChange={setFile}
            accept={ACCEPT}
            label={t("receiptModal.dropLabel")}
            hint={t("receiptModal.dropHint")}
            clearLabel={t("receiptModal.remove")}
          />
        </Field>

        <Field label={t("receiptModal.paidAt")} optional optionalLabel={t("receiptModal.optional")}>
          <Input
            className="ltr"
            type="date"
            value={paidAt}
            onChange={(e) => setPaidAt(e.target.value)}
          />
        </Field>
      </div>
    </Modal>
  );
}
