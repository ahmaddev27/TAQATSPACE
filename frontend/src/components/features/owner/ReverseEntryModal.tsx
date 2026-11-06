"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Textarea } from "@/components/ui/Textarea";
import { reverseWalletEntry } from "@/lib/actions/financeWallet";
import type { WalletEntry } from "@/lib/types/financeWallet";
import { money } from "./format";

interface Props {
  customerId: string;
  entry: WalletEntry;
  onClose: () => void;
  onReversed: () => void;
}

/** Reverses one wrong deposit or forfeit. The ledger is never edited: a new entry is posted. */
export function ReverseEntryModal({ customerId, entry, onClose, onReversed }: Props) {
  const t = useTranslations("finance.wallet");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  // One key per opened form, so a double submit reverses once.
  const [requestKey] = useState(() => crypto.randomUUID());
  const [reason, setReason] = useState("");
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | undefined>();

  const submit = () =>
    startTransition(async () => {
      const res = await reverseWalletEntry(
        customerId,
        entry.id,
        { reason: reason.trim(), request_key: requestKey },
        code.trim() || undefined,
      );
      if (res.ok) {
        toast({ tone: "ok", title: t("done.reverse") });
        onReversed();
        return;
      }
      setError(res.errors?.reason?.[0]);
      toast({ tone: "err", title: t("error"), body: res.message });
    });

  return (
    <Modal
      title={t("reverseTitle")}
      icon="wallet"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="danger" loading={pending} disabled={reason.trim().length < 3} onClick={submit}>
            {t("reverse")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <p className="muted" style={{ margin: 0 }}>{t("reverseHint", { amount: money(entry.amount) })}</p>
        <Field label={t("fields.reason")} htmlFor="wallet-reverse-reason" error={error}>
          <Textarea id="wallet-reverse-reason" value={reason} onChange={(e) => setReason(e.target.value)} />
        </Field>
        <Field label={t("fields.twoFactor")} htmlFor="wallet-reverse-code" hint={t("twoFactorHint")} optional optionalLabel={t("optional")}>
          <Input id="wallet-reverse-code" inputMode="numeric" autoComplete="one-time-code" className="ltr" value={code} onChange={(e) => setCode(e.target.value)} />
        </Field>
      </div>
    </Modal>
  );
}
