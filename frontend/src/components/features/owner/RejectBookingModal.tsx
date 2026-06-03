"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { Textarea } from "@/components/ui/Textarea";

export interface RejectBookingModalProps {
  pending: boolean;
  onConfirm: (reason: string) => void;
  onClose: () => void;
}

/** Reject a booking with an optional reason. */
export function RejectBookingModal({
  pending,
  onConfirm,
  onClose,
}: RejectBookingModalProps) {
  const t = useTranslations("owner");
  const [reason, setReason] = useState("");

  return (
    <Modal
      title={t("bookings.rejectTitle")}
      icon="x"
      onClose={onClose}
      closeLabel={t("common.close")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t("common.cancel")}
          </Button>
          <Button
            variant="danger"
            icon="x"
            loading={pending}
            onClick={() => onConfirm(reason.trim())}
          >
            {t("bookings.confirmReject")}
          </Button>
        </>
      }
    >
      <p className="muted" style={{ marginBottom: 16, fontSize: "var(--fs-sm)" }}>
        {t("bookings.rejectPrompt")}
      </p>
      <Field label={t("bookings.rejectReason")}>
        <Textarea
          rows={3}
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          placeholder={t("bookings.rejectReasonPlaceholder")}
        />
      </Field>
    </Modal>
  );
}
