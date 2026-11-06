"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { issueCreditNote } from "@/lib/actions/financeCreditNotes";

export interface CreditNoteButtonProps {
  invoiceId: string;
  invoiceNumber: string;
  /** Invoice total less credit notes already issued (decimal string). */
  creditable: string;
}

const MIN_REASON = 3;

/**
 * Owner action (finance v2): correct an issued invoice with a credit note
 * instead of editing it. Needs an amount and a reason; when the backend asks for
 * a two-factor step-up (403) the dialog also asks for a code.
 */
export function CreditNoteButton({ invoiceId, invoiceNumber, creditable }: CreditNoteButtonProps) {
  const t = useTranslations("invoices.owner.creditNote");
  const { toast } = useToast();
  const [open, setOpen] = useState(false);
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("");
  const [code, setCode] = useState("");
  const [needsCode, setNeedsCode] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  // One key per opened form: a double click or a retry repeats the same request, never a second credit.
  const [requestKey, setRequestKey] = useState("");

  const openForm = () => {
    setRequestKey(crypto.randomUUID());
    setOpen(true);
  };

  const close = () => {
    if (pending) return;
    setOpen(false);
    setAmount("");
    setReason("");
    setCode("");
    setNeedsCode(false);
    setError(null);
  };

  const submit = () => {
    if (!(Number(amount) > 0)) {
      setError(t("amountRequired"));
      return;
    }
    if (reason.trim().length < MIN_REASON) {
      setError(t("reasonRequired"));
      return;
    }
    setError(null);
    startTransition(async () => {
      const res = await issueCreditNote(invoiceId, amount, reason.trim(), code.trim() || undefined, requestKey);
      if (res.ok) {
        toast({ tone: "ok", title: t("done") });
        close();
      } else if (res.status === 403) {
        setNeedsCode(true);
        setError(t("codeRequired"));
      } else {
        toast({ tone: "err", title: t("failed"), body: res.message });
      }
    });
  };

  return (
    <>
      <button
        type="button"
        className="btn btn-ghost btn-sm"
        onClick={openForm}
        aria-label={t("title")}
        title={t("title")}
      >
        <Icon name="receipt" size={15} />
      </button>

      {open && (
        <Modal
          title={t("title")}
          icon="receipt"
          onClose={close}
          footer={
            <>
              <Button variant="ghost" onClick={close}>
                {t("cancel")}
              </Button>
              <Button variant="primary" icon="check" loading={pending} onClick={submit}>
                {t("confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 14 }}>
            <p className="muted">{t("body", { invoice: invoiceNumber, creditable })}</p>
            <Field label={t("amount")}>
              <Input
                type="number"
                min={0}
                step="0.01"
                className="ltr"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
            </Field>
            <Field label={t("reason")} error={needsCode ? undefined : (error ?? undefined)}>
              <Textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={t("reasonPlaceholder")}
                rows={3}
              />
            </Field>
            {needsCode && (
              <Field label={t("code")} error={error ?? undefined}>
                <Input
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  inputMode="numeric"
                  maxLength={6}
                  autoComplete="one-time-code"
                />
              </Field>
            )}
          </div>
        </Modal>
      )}
    </>
  );
}
