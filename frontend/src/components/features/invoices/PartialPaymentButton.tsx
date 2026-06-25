"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { useToast } from "@/components/providers/ToastProvider";
import { recordPartialPayment } from "@/lib/actions/invoices";

export interface PartialPaymentButtonProps {
  invoiceId: string;
  invoiceNumber: string;
  /** Outstanding balance (decimal string) used to seed + cap the amount. */
  remaining: string;
  currency: string;
}

/**
 * Owner: record a partial (or final) payment against an invoice. Opens a modal
 * showing the outstanding balance with an amount field (seeded to the balance)
 * and an optional payment date.
 */
export function PartialPaymentButton({
  invoiceId,
  invoiceNumber,
  remaining,
  currency,
}: PartialPaymentButtonProps) {
  const t = useTranslations("invoices.owner.partial");
  const { toast } = useToast();
  const [open, setOpen] = useState(false);
  const [amount, setAmount] = useState(remaining);
  const [paidAt, setPaidAt] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  const close = () => {
    if (pending) return;
    setOpen(false);
    setAmount(remaining);
    setPaidAt("");
    setError(null);
  };

  const submit = () => {
    const value = Number(amount);
    if (!value || value <= 0) {
      setError(t("amountRequired"));
      return;
    }
    setError(null);
    startTransition(async () => {
      const res = await recordPartialPayment(invoiceId, value, paidAt || null);
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
        variant="secondary"
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
            <p className="muted">
              {t("body", { invoice: invoiceNumber })}
            </p>
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

            <Field label={t("paidAt")} optional optionalLabel={t("optional")}>
              <Input
                className="ltr"
                type="date"
                value={paidAt}
                onChange={(e) => setPaidAt(e.target.value)}
              />
            </Field>
          </div>
        </Modal>
      )}
    </>
  );
}
