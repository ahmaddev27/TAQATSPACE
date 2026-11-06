"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Textarea } from "@/components/ui/Textarea";
import { voidPaymentVoucher } from "@/lib/actions/financeWallet";
import type { OpenInvoice, ReceiptCustomer } from "@/lib/types/financeReceipts";
import type { PaymentVoucher, WalletAction, WalletRow } from "@/lib/types/financeWallet";
import { WalletActionModal } from "./WalletActionModal";
import { WalletEntriesModal } from "./WalletEntriesModal";
import { money, shortDate } from "./format";

interface Props {
  wallets: WalletRow[];
  vouchers: PaymentVoucher[];
  customers: ReceiptCustomer[];
  invoices: OpenInvoice[];
}

interface Active {
  action: WalletAction;
  row: WalletRow | null;
}

/** Owner wallets: balances per customer with the four movements, and the payment vouchers. */
export function WalletManager({ wallets, vouchers, customers, invoices }: Props) {
  const t = useTranslations("finance.wallet");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [active, setActive] = useState<Active | null>(null);
  const [voiding, setVoiding] = useState<PaymentVoucher | null>(null);
  const [viewing, setViewing] = useState<WalletRow | null>(null);
  const [reason, setReason] = useState("");
  const [code, setCode] = useState("");

  const onVoid = () =>
    startTransition(async () => {
      if (!voiding) return;
      const res = await voidPaymentVoucher(voiding.id, reason, code.trim() || undefined);
      toast(res.ok ? { tone: "ok", title: t("voided") } : { tone: "err", title: t("error"), body: res.message });
      if (res.ok) {
        setVoiding(null);
        setReason("");
        setCode("");
      }
    });

  const hasBalance = (row: WalletRow) => Number(row.balance) > 0;

  const walletColumns: DataTableColumn<WalletRow>[] = [
    { id: "customer", header: t("fields.customer"), cell: (r) => r.customer.name },
    { id: "balance", header: t("fields.balance"), num: true, cell: (r) => money(r.balance) },
    { id: "last", header: t("fields.lastEntry"), cell: (r) => shortDate(r.last_entry_at) },
    {
      id: "actions",
      header: "",
      cell: (r) => (
        <div className="row wrap" style={{ gap: 6 }}>
          <Button variant="secondary" size="sm" onClick={() => setActive({ action: "deposit", row: r })}>{t("receive")}</Button>
          <Button variant="secondary" size="sm" disabled={!hasBalance(r)} onClick={() => setActive({ action: "return", row: r })}>{t("return")}</Button>
          <Button variant="secondary" size="sm" disabled={!hasBalance(r)} onClick={() => setActive({ action: "settle", row: r })}>{t("settle")}</Button>
          <Button variant="ghost" size="sm" disabled={!hasBalance(r)} onClick={() => setActive({ action: "forfeit", row: r })}>{t("forfeit")}</Button>
          <Button variant="ghost" size="sm" onClick={() => setViewing(r)}>{t("entries")}</Button>
        </div>
      ),
    },
  ];

  const voucherColumns: DataTableColumn<PaymentVoucher>[] = [
    { id: "number", header: t("fields.number"), cell: (v) => <span className="ltr">{v.number}</span> },
    { id: "customer", header: t("fields.customer"), cell: (v) => v.customer?.name ?? "—" },
    { id: "method", header: t("fields.method"), cell: (v) => t(`methods.${v.method}`) },
    { id: "amount", header: t("fields.amount"), num: true, cell: (v) => money(v.amount) },
    { id: "paidAt", header: t("fields.paidAt"), cell: (v) => shortDate(v.paid_at) },
    {
      id: "status",
      header: t("fields.status"),
      cell: (v) =>
        v.voided_at ? <Badge tone="danger">{t("statuses.voided")}</Badge> : <Badge tone="success">{t("statuses.active")}</Badge>,
    },
    {
      id: "actions",
      header: "",
      cell: (v) => (
        <div className="row wrap" style={{ gap: 6 }}>
          <a className="btn btn-ghost btn-sm" href={`/api/payment-vouchers/${v.id}/pdf`} download rel="noopener">
            <Icon name="download" size={15} />
            {t("downloadPdf")}
          </a>
          {!v.voided_at && <Button variant="ghost" size="sm" icon="trash" aria-label={t("void")} onClick={() => setVoiding(v)} />}
        </div>
      ),
    },
  ];

  return (
    <div className="stack" style={{ gap: 20 }}>
      <section className="card card-pad stack" style={{ gap: 16 }}>
        <div className="between row wrap" style={{ gap: 10 }}>
          <h2 className="h2" style={{ margin: 0 }}>{t("title")}</h2>
          <Button variant="primary" icon="plus" onClick={() => setActive({ action: "deposit", row: null })}>
            {t("receive")}
          </Button>
        </div>
        <DataTable
          columns={walletColumns}
          rows={wallets}
          rowKey={(r) => r.customer.id}
          empty={<p className="muted" style={{ margin: 0 }}>{t("empty")}</p>}
        />
      </section>

      <section className="card card-pad stack" style={{ gap: 16 }}>
        <h2 className="h2" style={{ margin: 0 }}>{t("vouchersTitle")}</h2>
        <DataTable
          columns={voucherColumns}
          rows={vouchers}
          rowKey={(v) => v.id}
          empty={<p className="muted" style={{ margin: 0 }}>{t("vouchersEmpty")}</p>}
        />
      </section>

      {active && (
        <WalletActionModal
          action={active.action}
          customerId={active.row?.customer.id ?? null}
          balance={active.row?.balance}
          customers={customers}
          invoices={invoices}
          onClose={() => setActive(null)}
        />
      )}

      {viewing && <WalletEntriesModal row={viewing} onClose={() => setViewing(null)} />}

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
          <div className="stack" style={{ gap: 14 }}>
            <Field label={t("voidReason")} htmlFor="wallet-void-reason" hint={t("voidHint", { number: voiding.number })}>
              <Textarea id="wallet-void-reason" value={reason} onChange={(e) => setReason(e.target.value)} />
            </Field>
            <Field label={t("fields.twoFactor")} htmlFor="wallet-code" hint={t("twoFactorHint")} optional optionalLabel={t("optional")}>
              <Input id="wallet-code" inputMode="numeric" autoComplete="one-time-code" className="ltr" value={code} onChange={(e) => setCode(e.target.value)} />
            </Field>
          </div>
        </Modal>
      )}
    </div>
  );
}
