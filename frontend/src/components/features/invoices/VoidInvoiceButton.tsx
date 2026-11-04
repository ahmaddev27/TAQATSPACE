"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Textarea } from "@/components/ui/Textarea";
import { Icon } from "@/components/ui/Icon";
import { useToast } from "@/components/providers/ToastProvider";
import { voidInvoice } from "@/lib/actions/invoices";

export interface VoidInvoiceButtonProps {
  invoiceId: string;
  invoiceNumber: string;
}

const MIN_REASON = 3;

/**
 * Owner action (finance v2): void an issued invoice. Needs a reason; when the
 * backend asks for a two-factor step-up (403) the dialog also asks for a code.
 */
export function VoidInvoiceButton({ invoiceId, invoiceNumber }: VoidInvoiceButtonProps) {
  const t = useTranslations("invoices.owner.void");
  const { toast } = useToast();
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [code, setCode] = useState("");
  const [needsCode, setNeedsCode] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  const close = () => {
    if (pending) return;
    setOpen(false);
    setReason("");
    setCode("");
    setNeedsCode(false);
    setError(null);
  };

  const submit = () => {
    if (reason.trim().length < MIN_REASON) {
      setError(t("reasonRequired"));
      return;
    }
    setError(null);
    startTransition(async () => {
      const res = await voidInvoice(invoiceId, reason.trim(), code.trim() || undefined);
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
        onClick={() => setOpen(true)}
        aria-label={t("title")}
        title={t("title")}
      >
        <Icon name="x" size={15} />
      </button>

      {open && (
        <Modal
          title={t("title")}
          icon="x"
          onClose={close}
          footer={
            <>
              <Button variant="ghost" onClick={close}>
                {t("cancel")}
              </Button>
              <Button variant="danger" icon="x" loading={pending} onClick={submit}>
                {t("confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 14 }}>
            <p className="muted">{t("body", { invoice: invoiceNumber })}</p>
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
