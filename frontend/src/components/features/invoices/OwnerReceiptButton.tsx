"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { FileDropzone } from "@/components/ui/FileDropzone";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { useToast } from "@/components/providers/ToastProvider";
import { uploadOwnerReceipt } from "@/lib/actions/invoices";

const ACCEPT = "application/pdf,image/png,image/jpeg,image/jpg";

export interface OwnerReceiptButtonProps {
  invoiceId: string;
  invoiceNumber: string;
}

/**
 * Owner: record a manual payment with proof. Opens a modal to pick the payment
 * date (optional, defaults to now) and attach the receipt; the backend stores
 * the file AND marks the invoice paid in one call.
 */
export function OwnerReceiptButton({
  invoiceId,
  invoiceNumber,
}: OwnerReceiptButtonProps) {
  const t = useTranslations("invoices.owner");
  const { toast } = useToast();
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const [file, setFile] = useState<File | null>(null);
  const [paidAt, setPaidAt] = useState("");
  const [error, setError] = useState<string | null>(null);

  const close = () => {
    if (pending) return;
    setOpen(false);
    setFile(null);
    setPaidAt("");
    setError(null);
  };

  const submit = () => {
    if (!file) {
      setError(t("receiptModal.fileRequired"));
      return;
    }
    setError(null);
    startTransition(async () => {
      const res = await uploadOwnerReceipt(invoiceId, file, paidAt || null);
      if (res.ok) {
        toast({ tone: "ok", title: t("receipt.uploaded") });
        close();
      } else {
        toast({
          tone: "err",
          title: t("receipt.uploadFailed"),
          body: res.message,
        });
      }
    });
  };

  return (
    <>
      <Button
        variant="secondary"
        size="sm"
        icon="upload"
        onClick={() => setOpen(true)}
      >
        {t("actions.uploadReceipt")}
      </Button>

      {open && (
        <Modal
          title={t("receiptModal.title")}
          icon="upload"
          onClose={close}
          footer={
            <>
              <Button variant="ghost" onClick={close}>
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
              {t("receiptModal.body", { invoice: invoiceNumber })}
            </p>

            <Field
              label={t("receiptModal.paidAt")}
              optional
              optionalLabel={t("receiptModal.optional")}
            >
              <Input
                className="ltr"
                type="date"
                value={paidAt}
                onChange={(e) => setPaidAt(e.target.value)}
              />
            </Field>

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
          </div>
        </Modal>
      )}
    </>
  );
}
