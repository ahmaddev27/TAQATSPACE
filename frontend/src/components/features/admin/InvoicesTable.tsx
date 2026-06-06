"use client";

import { useMemo, useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { StatusBadge } from "@/components/ui/Badge";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Tabs } from "@/components/ui/Tabs";
import { Icon } from "@/components/ui/Icon";
import {
  DataTable,
  Pager,
  sortRows,
  type DataTableColumn,
  type SortState,
} from "@/components/ui/DataTable";
import { useToast } from "@/components/providers/ToastProvider";
import { markInvoicePaid, markInvoiceUnpaid } from "@/lib/actions/admin";
import type { AdminInvoice } from "@/lib/api/admin";
import type { InvoiceStatus } from "@/lib/types";
import { invoiceMoney } from "@/components/features/invoices/format";
import { adminDate } from "./format";
import { ReceiptUploadModal } from "./ReceiptUploadModal";

type StatusTab = "all" | InvoiceStatus;

const TABS: StatusTab[] = ["all", "paid", "pending", "overdue", "cancelled"];
const PAGE_SIZE = 10;

export interface InvoicesTableProps {
  invoices: AdminInvoice[];
}

/** Super-admin invoice tracking: mark paid/unpaid, upload receipt, PDF/receipt. */
export function InvoicesTable({ invoices }: InvoicesTableProps) {
  const t = useTranslations("admin.invoices");
  const locale = useLocale();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [tab, setTab] = useState<StatusTab>("all");
  const [query, setQuery] = useState("");
  const [sort, setSort] = useState<SortState | null>({ key: "due", dir: "desc" });
  const [page, setPage] = useState(1);

  const [receiptTarget, setReceiptTarget] = useState<AdminInvoice | null>(null);
  const [payTarget, setPayTarget] = useState<AdminInvoice | null>(null);
  const [payDate, setPayDate] = useState("");

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let rows = invoices.filter((inv) => tab === "all" || inv.status === tab);
    if (q) {
      rows = rows.filter(
        (inv) =>
          inv.invoice_number.toLowerCase().includes(q) ||
          (inv.member?.name ?? "").toLowerCase().includes(q) ||
          (inv.workspace?.name ?? "").toLowerCase().includes(q),
      );
    }
    return sortRows(rows, sort, {
      invoice: (inv) => inv.invoice_number,
      member: (inv) => inv.member?.name ?? "",
      workspace: (inv) => inv.workspace?.name ?? "",
      amount: (inv) => Number(inv.amount),
      due: (inv) => inv.due_date ?? "",
      status: (inv) => inv.status,
      paid: (inv) => inv.paid_at ?? "",
    });
  }, [invoices, tab, query, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const runUnpaid = (invoice: AdminInvoice) => {
    startTransition(async () => {
      const res = await markInvoiceUnpaid(invoice.id);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.markedUnpaid") });
      } else {
        toast({ tone: "err", title: t("toast.markFailed"), body: res.message });
      }
    });
  };

  const submitPaid = () => {
    if (!payTarget) return;
    startTransition(async () => {
      const res = await markInvoicePaid(payTarget.id, payDate || null);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.markedPaid") });
        setPayTarget(null);
        setPayDate("");
      } else {
        toast({ tone: "err", title: t("toast.markFailed"), body: res.message });
      }
    });
  };

  const columns: DataTableColumn<AdminInvoice>[] = [
    {
      id: "invoice",
      header: t("colInvoice"),
      sortable: true,
      cell: (inv) => (
        <span className="cell-num ltr" style={{ fontWeight: 600 }}>
          {inv.invoice_number}
        </span>
      ),
    },
    {
      id: "member",
      header: t("colMember"),
      sortable: true,
      cell: (inv) => <span>{inv.member?.name ?? "—"}</span>,
    },
    {
      id: "workspace",
      header: t("colWorkspace"),
      sortable: true,
      cell: (inv) => <span className="muted">{inv.workspace?.name ?? "—"}</span>,
    },
    {
      id: "amount",
      header: t("colAmount"),
      num: true,
      sortable: true,
      cell: (inv) => (
        <span className="cell-num" style={{ fontWeight: 600 }}>
          {invoiceMoney(inv.amount, inv.currency)}
        </span>
      ),
    },
    {
      id: "due",
      header: t("colDue"),
      num: true,
      sortable: true,
      cell: (inv) => <span className="cell-num ltr">{adminDate(inv.due_date)}</span>,
    },
    {
      id: "status",
      header: t("colStatus"),
      sortable: true,
      cell: (inv) => <StatusBadge status={inv.status} locale={locale} />,
    },
    {
      id: "paid",
      header: t("colPaidAt"),
      num: true,
      sortable: true,
      cell: (inv) => <span className="cell-num ltr">{adminDate(inv.paid_at)}</span>,
    },
    {
      id: "actions",
      header: "",
      cell: (inv) => (
        <div className="row-actions">
          {inv.status === "paid" ? (
            <Button
              variant="secondary"
              size="sm"
              icon="x"
              loading={pending}
              onClick={() => runUnpaid(inv)}
            >
              {t("markUnpaid")}
            </Button>
          ) : (
            inv.status !== "cancelled" && (
              <Button
                variant="primary"
                size="sm"
                icon="check"
                loading={pending}
                onClick={() => {
                  setPayDate("");
                  setPayTarget(inv);
                }}
              >
                {t("markPaid")}
              </Button>
            )
          )}
          <Button
            variant="ghost"
            size="sm"
            icon="upload"
            onClick={() => setReceiptTarget(inv)}
          >
            {t("uploadReceipt")}
          </Button>
          {inv.pdf_url && (
            <a
              className="btn btn-ghost btn-sm"
              href={inv.pdf_url}
              target="_blank"
              rel="noopener noreferrer"
            >
              <Icon name="download" size={15} />
              {t("downloadPdf")}
            </a>
          )}
          {inv.receipt_url && (
            <a
              className="btn btn-ghost btn-sm"
              href={inv.receipt_url}
              target="_blank"
              rel="noopener noreferrer"
            >
              <Icon name="eye" size={15} />
              {t("viewReceipt")}
            </a>
          )}
        </div>
      ),
    },
  ];

  const tabs = TABS.map((id) => ({ id, label: t(`tabs.${id}`) }));

  return (
    <div className="dt" style={{ gap: 18 }}>
      <div className="dt-toolbar">
        <Tabs
          items={tabs}
          value={tab}
          onChange={(v) => {
            setTab(v as StatusTab);
            setPage(1);
          }}
        />
        <div className="spacer" />
        <div className="dt-search input-icon">
          <Icon name="search" />
          <input
            className="input"
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setPage(1);
            }}
            placeholder={t("searchPlaceholder")}
          />
        </div>
      </div>

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={pageRows}
          rowKey={(inv) => inv.id}
          sort={sort}
          setSort={setSort}
          empty={
            <div className="empty-state">
              <div>
                <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                  {t("emptyTitle")}
                </div>
                <div style={{ fontSize: "var(--fs-sm)" }}>{t("emptyBody")}</div>
              </div>
            </div>
          }
        />
        {filtered.length > 0 && (
          <div className="table-foot">
            <span>
              {t("count")} <strong>{filtered.length}</strong>
            </span>
            <Pager page={curPage} pages={pages} setPage={setPage} />
          </div>
        )}
      </div>

      {payTarget && (
        <Modal
          title={t("payModal.title")}
          icon="check"
          onClose={() => setPayTarget(null)}
          footer={
            <>
              <Button variant="ghost" onClick={() => setPayTarget(null)}>
                {t("payModal.cancel")}
              </Button>
              <Button
                variant="primary"
                icon="check"
                loading={pending}
                onClick={submitPaid}
              >
                {t("payModal.confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted">
              {t("payModal.body", { invoice: payTarget.invoice_number })}
            </p>
            <Field
              label={t("payModal.paidAt")}
              optional
              optionalLabel={t("payModal.optional")}
            >
              <Input
                className="ltr"
                type="date"
                value={payDate}
                onChange={(e) => setPayDate(e.target.value)}
              />
            </Field>
          </div>
        </Modal>
      )}

      {receiptTarget && (
        <ReceiptUploadModal
          invoice={receiptTarget}
          onClose={() => setReceiptTarget(null)}
        />
      )}
    </div>
  );
}
