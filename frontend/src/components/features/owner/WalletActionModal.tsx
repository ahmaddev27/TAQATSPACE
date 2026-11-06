"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { forfeitWallet, receiveDeposit, returnDeposit, settleWallet } from "@/lib/actions/financeWallet";
import type { ActionResult } from "@/lib/actions/client";
import type { OpenInvoice, ReceiptCustomer } from "@/lib/types/financeReceipts";
import { DEPOSIT_METHODS, WALLET_METHODS, type DepositMethod, type WalletAction, type WalletMethod } from "@/lib/types/financeWallet";
import { money } from "./format";

interface Props {
  action: WalletAction;
  /** Fixed customer, or null to let the owner choose one (receiving a deposit). */
  customerId: string | null;
  balance?: string;
  customers: ReceiptCustomer[];
  invoices: OpenInvoice[];
  onClose: () => void;
}

/** One form for the four wallet movements; only the fields each one needs are shown. */
export function WalletActionModal({ action, customerId: fixedCustomer, balance, customers, invoices, onClose }: Props) {
  const t = useTranslations("finance.wallet");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  // One key per opened modal (it mounts on click, so no hydration issue): a double submit posts once.
  const [requestKey] = useState(() => crypto.randomUUID());

  const [customerId, setCustomerId] = useState(fixedCustomer ?? "");
  const [amount, setAmount] = useState("");
  const [method, setMethod] = useState<WalletMethod>("cash");
  const [reason, setReason] = useState("");
  const [reference, setReference] = useState("");
  const [invoiceId, setInvoiceId] = useState("");
  const [code, setCode] = useState("");

  const open = invoices.filter((i) => i.customer_id === customerId);
  const needsMethod = action === "deposit" || action === "return";
  const needsReason = action === "return" || action === "forfeit";
  const stepUp = action === "return" || action === "forfeit";

  const run = (): Promise<ActionResult<unknown>> => {
    const twoFactor = code.trim() || undefined;
    switch (action) {
      case "deposit":
        return receiveDeposit(customerId, { amount, method: method as DepositMethod, reason: reason || undefined, request_key: requestKey });
      case "return":
        return returnDeposit(customerId, { amount, method, reason, reference: reference || undefined, request_key: requestKey }, twoFactor);
      case "settle":
        return settleWallet(customerId, { invoice_id: invoiceId, amount, request_key: requestKey });
      case "forfeit":
        return forfeitWallet(customerId, { amount, reason, request_key: requestKey }, twoFactor);
    }
  };

  const submit = () =>
    startTransition(async () => {
      const res = await run();
      if (res.ok) {
        toast({ tone: "ok", title: t(`done.${action}`) });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  const ready =
    customerId !== "" &&
    Number(amount) > 0 &&
    (!needsReason || reason.trim().length >= 3) &&
    (action !== "settle" || invoiceId !== "");

  return (
    <Modal
      title={t(`actionTitles.${action}`)}
      icon="wallet"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant={action === "forfeit" ? "danger" : "primary"} loading={pending} disabled={!ready} onClick={submit}>
            {t("save")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        {fixedCustomer === null && (
          <Field label={t("fields.customer")} error={errors.customer_id?.[0]}>
            <Select value={customerId} onChange={(e) => setCustomerId(e.target.value)}>
              <option value="">{t("chooseCustomer")}</option>
              {customers.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </Select>
          </Field>
        )}
        {balance !== undefined && <p className="muted" style={{ margin: 0 }}>{t("balanceHint", { amount: money(balance) })}</p>}

        {action === "settle" && (
          <Field label={t("fields.invoice")} htmlFor="wallet-invoice" error={errors.invoice_id?.[0]} hint={open.length === 0 ? t("noInvoices") : undefined}>
            <Select id="wallet-invoice" value={invoiceId} onChange={(e) => setInvoiceId(e.target.value)}>
              <option value="">{t("chooseInvoice")}</option>
              {open.map((i) => (
                <option key={i.id} value={i.id}>{i.invoice_number} ({money(i.remaining_amount)})</option>
              ))}
            </Select>
          </Field>
        )}

        <div className="row wrap" style={{ gap: 12 }}>
          <Field label={t("fields.amount")} htmlFor="wallet-amount" error={errors.amount?.[0]} className="grow">
            <Input id="wallet-amount" type="number" min={0} step="0.01" className="ltr" value={amount} onChange={(e) => setAmount(e.target.value)} />
          </Field>
          {needsMethod && (
            <Field label={t("fields.method")} htmlFor="wallet-method" error={errors.method?.[0]} className="grow">
              <Select id="wallet-method" value={method} onChange={(e) => setMethod(e.target.value as WalletMethod)}>
                {(action === "deposit" ? DEPOSIT_METHODS : WALLET_METHODS).map((m) => (
                  <option key={m} value={m}>{t(`methods.${m}`)}</option>
                ))}
              </Select>
            </Field>
          )}
        </div>

        {action !== "settle" && (
          <Field
            label={t("fields.reason")}
            htmlFor="wallet-reason"
            error={errors.reason?.[0]}
            optional={!needsReason}
            optionalLabel={t("optional")}
          >
            <Input id="wallet-reason" value={reason} onChange={(e) => setReason(e.target.value)} />
          </Field>
        )}
        {action === "return" && (
          <Field label={t("fields.reference")} error={errors.reference?.[0]} optional optionalLabel={t("optional")}>
            <Input className="ltr" value={reference} onChange={(e) => setReference(e.target.value)} />
          </Field>
        )}
        {stepUp && (
          <Field label={t("fields.twoFactor")} htmlFor="wallet-code" hint={t("twoFactorHint")} optional optionalLabel={t("optional")}>
            <Input id="wallet-code" inputMode="numeric" autoComplete="one-time-code" className="ltr" value={code} onChange={(e) => setCode(e.target.value)} />
          </Field>
        )}
      </div>
    </Modal>
  );
}
