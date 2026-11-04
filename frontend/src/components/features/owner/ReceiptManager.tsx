"use client";

import { useState, useTransition } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Field } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { Segmented } from "@/components/ui/Segmented";
import { Textarea } from "@/components/ui/Textarea";
import { changeChequeStatus, voidReceipt } from "@/lib/actions/financeReceipts";
import {
  CHEQUE_NEXT,
  type OpenInvoice,
  type Receipt,
  type ReceiptCustomer,
  type ReceiptSort,
} from "@/lib/types/financeReceipts";
import { ReceiptFormModal } from "./ReceiptFormModal";
import { money, shortDate } from "./format";

interface Props {
  receipts: Receipt[];
  sort: ReceiptSort;
  customers: ReceiptCustomer[];
  invoices: OpenInvoice[];
}

/** Owner receipts: list sorted by receipt or collection date, with cheque and void actions. */
export function ReceiptManager({ receipts, sort, customers, invoices }: Props) {
  const t = useTranslations("finance.receipts");
  const router = useRouter();
  const pathname = usePathname();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [creating, setCreating] = useState(false);
  const [voiding, setVoiding] = useState<Receipt | null>(null);
  const [reason, setReason] = useState("");

  const report = (ok: boolean, title: string, body?: string) =>
    toast(ok ? { tone: "ok", title } : { tone: "err", title: t("error"), body });

  const onCheque = (receipt: Receipt, status: Parameters<typeof changeChequeStatus>[1]) =>
    startTransition(async () => {
      const res = await changeChequeStatus(receipt.id, status);
      report(res.ok, t("chequeUpdated"), res.ok ? undefined : res.message);
    });

  const onVoid = () =>
    startTransition(async () => {
      if (!voiding) return;
      const res = await voidReceipt(voiding.id, reason);
      report(res.ok, t("voided"), res.ok ? undefined : res.message);
      if (res.ok) {
        setVoiding(null);
        setReason("");
      }
    });

  const columns: DataTableColumn<Receipt>[] = [
    { id: "number", header: t("fields.number"), cell: (r) => <span className="ltr">{r.number}</span> },
    { id: "customer", header: t("fields.customer"), cell: (r) => r.customer?.name ?? "—" },
    { id: "method", header: t("fields.method"), cell: (r) => t(`methods.${r.method}`) },
    { id: "amount", header: t("fields.amount"), num: true, cell: (r) => money(r.amount) },
    { id: "unallocated", header: t("fields.unallocated"), num: true, cell: (r) => money(r.unallocated_amount) },
    { id: "receivedAt", header: t("fields.receivedAt"), cell: (r) => shortDate(r.received_at) },
    { id: "collectedAt", header: t("fields.collectedAt"), cell: (r) => shortDate(r.collected_at) },
    {
      id: "status",
      header: t("fields.status"),
      cell: (r) =>
        r.voided_at ? (
          <Badge tone="danger">{t("statuses.voided")}</Badge>
        ) : r.cheque_status ? (
          <Badge tone={r.cheque_status === "cleared" ? "success" : r.cheque_status === "bounced" ? "danger" : "warning"}>
            {t(`cheque.${r.cheque_status}`)}
          </Badge>
        ) : (
          <Badge tone="success">{t("statuses.collected")}</Badge>
        ),
    },
    {
      id: "actions",
      header: "",
      cell: (r) => (
        <div className="row wrap" style={{ gap: 6 }}>
          {r.receipt_url && (
            <a className="btn btn-ghost btn-sm" href={r.receipt_url} target="_blank" rel="noopener noreferrer">
              {t("viewProof")}
            </a>
          )}
          {!r.voided_at &&
            r.cheque_status &&
            CHEQUE_NEXT[r.cheque_status].map((next) => (
              <Button key={next} variant="secondary" size="sm" loading={pending} onClick={() => onCheque(r, next)}>
                {t(`chequeActions.${next}`)}
              </Button>
            ))}
          {!r.voided_at && (
            <Button variant="ghost" size="sm" icon="trash" aria-label={t("void")} onClick={() => setVoiding(r)} />
          )}
        </div>
      ),
    },
  ];

  return (
    <section className="card card-pad stack" style={{ gap: 16 }}>
      <div className="between row wrap" style={{ gap: 10 }}>
        <Segmented
          items={[
            { id: "received_at", label: t("sort.receivedAt") },
            { id: "collected_at", label: t("sort.collectedAt") },
          ]}
          value={sort}
          onChange={(id) => router.push(`${pathname}?sort=${id}`)}
        />
        <Button variant="primary" icon="plus" onClick={() => setCreating(true)}>
          {t("add")}
        </Button>
      </div>

      <DataTable
        columns={columns}
        rows={receipts}
        rowKey={(r) => r.id}
        empty={<p className="muted" style={{ margin: 0 }}>{t("empty")}</p>}
      />

      {creating && <ReceiptFormModal customers={customers} invoices={invoices} onClose={() => setCreating(false)} />}

      {voiding && (
        <Modal
          title={t("voidTitle")}
          icon="trash"
          onClose={() => setVoiding(null)}
          footer={
            <>
              <Button variant="ghost" onClick={() => setVoiding(null)}>{t("cancel")}</Button>
              <Button variant="danger" loading={pending} disabled={reason.trim().length < 3} onClick={onVoid}>
                {t("void")}
              </Button>
            </>
          }
        >
          <Field label={t("voidReason")} hint={t("voidHint", { number: voiding.number })}>
            <Textarea value={reason} onChange={(e) => setReason(e.target.value)} />
          </Field>
        </Modal>
      )}
    </section>
  );
}
