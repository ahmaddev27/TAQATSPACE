"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { Textarea } from "@/components/ui/Textarea";
import { Icon } from "@/components/ui/Icon";
import { useToast } from "@/components/providers/ToastProvider";
import { approveReceipt, rejectReceipt } from "@/lib/actions/invoices";

export interface ReceiptReviewActionsProps {
  invoiceId: string;
  invoiceNumber: string;
  receiptUrl: string | null;
}

/**
 * Owner review of a member-submitted receipt (status "under review"): view the
 * proof, then approve (confirm paid) or reject with a reason so the member can
 * fix it and re-upload.
 */
export function ReceiptReviewActions({
  invoiceId,
  invoiceNumber,
  receiptUrl,
}: ReceiptReviewActionsProps) {
  const t = useTranslations("invoices.owner.review");
  const { toast } = useToast();
  const [rejectOpen, setRejectOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  const approve = () => {
    startTransition(async () => {
      const res = await approveReceipt(invoiceId);
      if (res.ok) toast({ tone: "ok", title: t("approved") });
      else toast({ tone: "err", title: t("approveFailed"), body: res.message });
    });
  };

  const closeReject = () => {
    if (pending) return;
    setRejectOpen(false);
    setReason("");
    setError(null);
  };

  const reject = () => {
    if (reason.trim().length < 3) {
      setError(t("reasonRequired"));
      return;
    }
    setError(null);
    startTransition(async () => {
      const res = await rejectReceipt(invoiceId, reason.trim());
      if (res.ok) {
        toast({ tone: "ok", title: t("rejected") });
        closeReject();
      } else {
        toast({ tone: "err", title: t("rejectFailed"), body: res.message });
      }
    });
  };

  return (
    <>
      {receiptUrl && (
        <a
          href={receiptUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="btn btn-ghost btn-sm"
        >
          <Icon name="receipt" size={15} />
          {t("viewReceipt")}
        </a>
      )}
      <Button
        variant="primary"
        size="sm"
        icon="check"
        loading={pending}
        onClick={approve}
      >
        {t("approve")}
      </Button>
      <Button
        variant="danger"
        size="sm"
        icon="x"
        onClick={() => setRejectOpen(true)}
      >
        {t("reject")}
      </Button>

      {rejectOpen && (
        <Modal
          title={t("rejectModal.title")}
          icon="x"
          onClose={closeReject}
          footer={
            <>
              <Button variant="ghost" onClick={closeReject}>
                {t("rejectModal.cancel")}
              </Button>
              <Button
                variant="danger"
                icon="x"
                loading={pending}
                onClick={reject}
              >
                {t("rejectModal.confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 14 }}>
            <p className="muted">
              {t("rejectModal.body", { invoice: invoiceNumber })}
            </p>
            <Field label={t("rejectModal.reason")} error={error ?? undefined}>
              <Textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={t("rejectModal.reasonPlaceholder")}
                rows={3}
              />
            </Field>
          </div>
        </Modal>
      )}
    </>
  );
}
