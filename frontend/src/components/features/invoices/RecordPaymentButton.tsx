"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { FileDropzone } from "@/components/ui/FileDropzone";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { useImageCropper } from "@/components/ui/useImageCropper";
import { useToast } from "@/components/providers/ToastProvider";
import { recordPayment } from "@/lib/actions/invoices";

const ACCEPT = "application/pdf,image/png,image/jpeg,image/jpg";

export interface RecordPaymentButtonProps {
  invoiceId: string;
  invoiceNumber: string;
  /** Outstanding balance (decimal string) — seeds the amount and is shown. */
  remaining: string;
  currency: string;
}

/**
 * The single owner payment action. Records a payment (partial or full) against an
 * invoice with a required receipt and an optional date; the invoice becomes
 * partially paid, or paid once the balance reaches zero.
 */
export function RecordPaymentButton({
  invoiceId,
  invoiceNumber,
  remaining,
  currency,
}: RecordPaymentButtonProps) {
  const t = useTranslations("invoices.owner.payment");
  const { toast } = useToast();
  const [open, setOpen] = useState(false);
  const [amount, setAmount] = useState(remaining);
  const [paidAt, setPaidAt] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  // Receipts vary in shape, so cropping is OPTIONAL (a button) — the picked file
  // uploads as-is unless the owner chooses to crop/rotate it.
  const { cropFile, cropper } = useImageCropper({
    aspect: 3 / 4,
    maxSize: 1600,
    allowRotate: true,
  });

  const isImage = file != null && file.type.startsWith("image/");
  const previewUrl = useMemo(
    () => (isImage && file ? URL.createObjectURL(file) : null),
    [isImage, file],
  );
  useEffect(
    () => () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
    },
    [previewUrl],
  );

  const crop = async () => {
    if (!file) return;
    const cropped = await cropFile(file);
    if (cropped) setFile(cropped);
  };

  const close = () => {
    if (pending) return;
    setOpen(false);
    setAmount(remaining);
    setPaidAt("");
    setFile(null);
    setError(null);
    setFileError(null);
  };

  const submit = () => {
    const value = Number(amount);
    let ok = true;
    if (!value || value <= 0) {
      setError(t("amountRequired"));
      ok = false;
    } else {
      setError(null);
    }
    if (!file) {
      setFileError(t("receiptRequired"));
      ok = false;
    } else {
      setFileError(null);
    }
    if (!ok || !file) return;

    startTransition(async () => {
      const res = await recordPayment(invoiceId, value, file, paidAt || null);
      if (res.ok) {
        toast({ tone: "ok", title: t("recorded") });
        close();
      } else {
        toast({ tone: "err", title: t("failed"), body: res.message });
      }
    });
  };

  return (
    <>
      <Button
        variant="primary"
        size="sm"
        icon="wallet"
        onClick={() => setOpen(true)}
      >
        {t("action")}
      </Button>

      {open && (
        <Modal
          title={t("title")}
          icon="wallet"
          onClose={close}
          footer={
            <>
              <Button variant="ghost" onClick={close}>
                {t("cancel")}
              </Button>
              <Button
                variant="primary"
                icon="check"
                loading={pending}
                onClick={submit}
              >
                {t("confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted">{t("body", { invoice: invoiceNumber })}</p>

            <div className="between">
              <span className="muted">{t("remaining")}</span>
              <span className="cell-num" style={{ fontWeight: 700 }}>
                {currency} {remaining}
              </span>
            </div>

            <Field label={t("amount")} error={error ?? undefined}>
              <Input
                className="tnum ltr"
                inputMode="decimal"
                placeholder="0.00"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
            </Field>

            <Field label={t("receipt")} error={fileError ?? undefined}>
              <FileDropzone
                value={file}
                onChange={setFile}
                accept={ACCEPT}
                label={t("dropLabel")}
                hint={t("dropHint")}
                clearLabel={t("remove")}
              />
              {previewUrl && (
                <div className="stack" style={{ gap: 8, marginTop: 10 }}>
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={previewUrl}
                    alt={t("receipt")}
                    style={{
                      maxWidth: "100%",
                      maxHeight: 220,
                      borderRadius: "var(--r-md)",
                      border: "1px solid var(--border)",
                      objectFit: "contain",
                    }}
                  />
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    icon="edit"
                    onClick={crop}
                  >
                    {t("crop")}
                  </Button>
                </div>
              )}
            </Field>

            {/* Date last + full-width: the native calendar opens at the bottom
                of the modal instead of covering the fields above it. */}
            <Field label={t("paidAt")} optional optionalLabel={t("optional")}>
              <Input
                className="ltr"
                type="date"
                value={paidAt}
                onChange={(e) => setPaidAt(e.target.value)}
              />
            </Field>
          </div>

          {cropper}
        </Modal>
      )}
    </>
  );
}
