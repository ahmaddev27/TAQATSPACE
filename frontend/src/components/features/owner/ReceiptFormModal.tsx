"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { createReceipt } from "@/lib/actions/financeReceipts";
import {
  RECEIPT_METHODS,
  type OpenInvoice,
  type ReceiptCustomer,
  type ReceiptMethod,
} from "@/lib/types/financeReceipts";
import { money } from "./format";

interface Props {
  customers: ReceiptCustomer[];
  invoices: OpenInvoice[];
  onClose: () => void;
}

interface Row {
  invoiceId: string;
  amount: string;
}

/** New receipt: who paid, how much, and how it is spread over their open invoices. */
export function ReceiptFormModal({ customers, invoices, onClose }: Props) {
  const t = useTranslations("finance.receipts");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const [customerId, setCustomerId] = useState("");
  const [method, setMethod] = useState<ReceiptMethod>("cash");
  const [amount, setAmount] = useState("");
  const [receivedAt, setReceivedAt] = useState("");
  const [reference, setReference] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [rows, setRows] = useState<Row[]>([]);

  const open = useMemo(() => invoices.filter((i) => i.customer_id === customerId), [invoices, customerId]);
  const allocated = rows.reduce((sum, r) => sum + (Number(r.amount) || 0), 0);
  const surplus = (Number(amount) || 0) - allocated;

  const changeCustomer = (id: string) => {
    setCustomerId(id);
    setRows([]);
  };

  const patchRow = (index: number, patch: Partial<Row>) =>
    setRows((current) => current.map((r, i) => (i === index ? { ...r, ...patch } : r)));

  const addRow = () => {
    const free = open.find((i) => !rows.some((r) => r.invoiceId === i.id));
    if (free) setRows([...rows, { invoiceId: free.id, amount: free.remaining_amount }]);
  };

  const submit = () =>
    startTransition(async () => {
      const body = new FormData();
      body.set("customer_id", customerId);
      body.set("method", method);
      body.set("amount", amount);
      if (receivedAt) body.set("received_at", receivedAt);
      if (reference) body.set("reference", reference);
      if (file) body.set("receipt", file);
      rows.forEach((row, i) => {
        body.set(`allocations[${i}][invoice_id]`, row.invoiceId);
        body.set(`allocations[${i}][amount]`, row.amount);
      });

      const res = await createReceipt(body);
      if (res.ok) {
        toast({ tone: "ok", title: t("created") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <Modal
      title={t("createTitle")}
      icon="receipt"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="primary" loading={pending} disabled={!customerId || !amount || surplus < 0} onClick={submit}>
            {t("save")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <Field label={t("fields.customer")} error={errors.customer_id?.[0]}>
          <Select value={customerId} onChange={(e) => changeCustomer(e.target.value)}>
            <option value="">{t("chooseCustomer")}</option>
            {customers.map((c) => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </Select>
        </Field>
        <div className="row wrap" style={{ gap: 12 }}>
          <Field label={t("fields.method")} error={errors.method?.[0]} className="grow">
            <Select value={method} onChange={(e) => setMethod(e.target.value as ReceiptMethod)}>
              {RECEIPT_METHODS.map((m) => (
                <option key={m} value={m}>{t(`methods.${m}`)}</option>
              ))}
            </Select>
          </Field>
          <Field label={t("fields.amount")} error={errors.amount?.[0]} className="grow">
            <Input type="number" min={0} step="0.01" className="ltr" value={amount} onChange={(e) => setAmount(e.target.value)} />
          </Field>
        </div>
        <div className="row wrap" style={{ gap: 12 }}>
          <Field label={t("fields.receivedAt")} error={errors.received_at?.[0]} className="grow">
            <Input type="date" className="ltr" value={receivedAt} onChange={(e) => setReceivedAt(e.target.value)} />
          </Field>
          <Field
            label={method === "cheque" ? t("fields.chequeNumber") : t("fields.reference")}
            error={errors.reference?.[0]}
            className="grow"
          >
            <Input className="ltr" value={reference} onChange={(e) => setReference(e.target.value)} />
          </Field>
        </div>
        <Field label={t("fields.proof")} hint={t("proofHint")} error={errors.receipt?.[0]} optional optionalLabel={t("optional")}>
          <Input type="file" accept="application/pdf,image/png,image/jpeg" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
        </Field>

        <div className="stack" style={{ gap: 8 }}>
          <div className="between row">
            <strong>{t("allocations")}</strong>
            <Button variant="secondary" size="sm" icon="plus" disabled={!customerId || rows.length >= open.length} onClick={addRow}>
              {t("addAllocation")}
            </Button>
          </div>
          {rows.map((row, index) => (
            <div key={row.invoiceId} className="row" style={{ gap: 8 }}>
              <Select value={row.invoiceId} onChange={(e) => patchRow(index, { invoiceId: e.target.value })}>
                {open.map((i) => (
                  <option key={i.id} value={i.id}>{i.invoice_number} ({money(i.remaining_amount)})</option>
                ))}
              </Select>
              <Input type="number" min={0} step="0.01" className="ltr" aria-label={t("fields.amount")} value={row.amount} onChange={(e) => patchRow(index, { amount: e.target.value })} />
              <Button variant="ghost" size="sm" icon="trash" aria-label={t("removeAllocation")} onClick={() => setRows(rows.filter((_, i) => i !== index))} />
            </div>
          ))}
          {errors.allocations && <p className="muted" role="alert">{errors.allocations[0]}</p>}
          <p className="muted" style={{ margin: 0 }}>
            {surplus < 0 ? t("overAllocated") : t("surplus", { amount: money(surplus) })}
          </p>
        </div>
      </div>
    </Modal>
  );
}
