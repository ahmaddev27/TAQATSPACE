"use client";

import { useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { InvoiceStatusBadge } from "./InvoiceStatusBadge";
import { Select } from "@/components/ui/Select";
import { Tabs } from "@/components/ui/Tabs";
import {
  DataTable,
  Pager,
  sortRows,
  type DataTableColumn,
  type SortState,
} from "@/components/ui/DataTable";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import { ExportCsvLink } from "@/components/features/admin/ExportCsvLink";
import { Icon } from "@/components/ui/Icon";
import type { Invoice } from "@/lib/api/invoices";
import type { InvoiceStatus } from "@/lib/types";
import {
  IssueInvoiceModal,
  type InvoiceMemberOption,
} from "./IssueInvoiceModal";
import { MarkPaidButton } from "./MarkPaidButton";
import { RemindButton } from "./RemindButton";
import { DownloadPdfLink } from "./DownloadPdfLink";
import { invoiceDate, invoiceMoney, invoicePeriod } from "./format";

type StatusTab = "all" | InvoiceStatus;

const TABS: StatusTab[] = ["all", "paid", "pending", "overdue"];
const FILTER_KEYS = ["status", "month"] as const;
const PAGE_SIZE = 8;

export interface InvoicesTableProps {
  invoices: Invoice[];
  members: InvoiceMemberOption[];
}

/** Owner invoices: status/month filters, sortable table, issue modal. */
export function InvoicesTable({ invoices, members }: InvoicesTableProps) {
  const t = useTranslations("invoices.owner");
  const locale = useLocale();

  // Filters live in the URL so they are shareable and readable by the CSV
  // export link (the download then matches the on-screen view).
  const { filters, setFilter, clearFilters } = useUrlFilters(FILTER_KEYS);
  const tab: StatusTab = (filters.status as StatusTab) || "all";
  const month = filters.month;
  const [sort, setSort] = useState<SortState | null>({ key: "due", dir: "desc" });
  const [page, setPage] = useState(1);
  const [issueOpen, setIssueOpen] = useState(false);

  // Month options derived from the invoices in view (YYYY-MM, newest first).
  const monthOptions = useMemo(() => {
    const set = new Set<string>();
    for (const inv of invoices) {
      const ref = inv.due_date ?? inv.created_at;
      if (ref) set.add(ref.slice(0, 7));
    }
    return Array.from(set).sort().reverse();
  }, [invoices]);

  const filtered = useMemo(() => {
    let rows = invoices.filter((inv) => tab === "all" || inv.status === tab);
    if (month) {
      rows = rows.filter((inv) => {
        const ref = inv.due_date ?? inv.created_at;
        return ref ? ref.slice(0, 7) === month : false;
      });
    }
    return sortRows(rows, sort, {
      invoice: (inv) => inv.invoice_number,
      member: (inv) => inv.subscription?.member?.name ?? "",
      due: (inv) => inv.due_date ?? "",
      amount: (inv) => Number(inv.amount),
      status: (inv) => inv.status,
    });
  }, [invoices, tab, month, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const isFiltered = tab !== "all" || month !== "";
  const resetFilters = () => {
    clearFilters();
    setPage(1);
  };

  const tabs = TABS.map((id) => ({
    id,
    label: t(`filters.${id}`),
  }));

  const columns: DataTableColumn<Invoice>[] = [
    {
      id: "invoice",
      header: t("table.invoice"),
      sortable: true,
      cell: (inv) => (
        <span className="cell-num ltr" style={{ fontWeight: 600 }}>
          {inv.invoice_number}
        </span>
      ),
    },
    {
      id: "member",
      header: t("table.member"),
      sortable: true,
      cell: (inv) => <span>{inv.subscription?.member?.name ?? "—"}</span>,
    },
    {
      id: "period",
      header: t("table.period"),
      cell: (inv) => (
        <span className="muted ltr" style={{ fontSize: "var(--fs-sm)" }}>
          {invoicePeriod(
            inv.subscription?.start_date,
            inv.subscription?.end_date,
          )}
        </span>
      ),
    },
    {
      id: "due",
      header: t("table.due"),
      num: true,
      sortable: true,
      cell: (inv) => (
        <span className="cell-num ltr">{invoiceDate(inv.due_date)}</span>
      ),
    },
    {
      id: "amount",
      header: t("table.amount"),
      num: true,
      sortable: true,
      cell: (inv) => (
        <span className="cell-num" style={{ fontWeight: 600 }}>
          {invoiceMoney(inv.amount, inv.currency)}
        </span>
      ),
    },
    {
      id: "status",
      header: t("table.status"),
      sortable: true,
      cell: (inv) => <InvoiceStatusBadge status={inv.status} locale={locale} />,
    },
    {
      id: "actions",
      header: "",
      cell: (inv) => (
        <div className="row-actions">
          {inv.status !== "paid" && inv.status !== "cancelled" && (
            <>
              <MarkPaidButton invoiceId={inv.id} />
              <RemindButton invoiceId={inv.id} />
            </>
          )}
          {inv.receipt_url && (
            <a
              href={inv.receipt_url}
              target="_blank"
              rel="noopener noreferrer"
              className="btn btn-ghost btn-sm"
            >
              <Icon name="receipt" size={15} />
              {t("actions.viewReceipt")}
            </a>
          )}
          <DownloadPdfLink invoiceId={inv.id} label={t("actions.downloadPdf")} />
        </div>
      ),
    },
  ];

  return (
    <div className="dt" style={{ gap: 18 }}>
      <div className="dt-toolbar">
        <Tabs
          items={tabs}
          value={tab}
          onChange={(v) => {
            setFilter("status", v === "all" ? null : v);
            setPage(1);
          }}
        />
        <div className="spacer" />
        <span className="dt-count">
          {t("filters.count")} <strong>{filtered.length}</strong>
        </span>
        <div className="dt-tools">
          {monthOptions.length > 0 && (
            <Select
              value={month}
              onChange={(e) => {
                setFilter("month", e.target.value);
                setPage(1);
              }}
              aria-label={t("filters.month")}
            >
              <option value="">{t("filters.anyMonth")}</option>
              {monthOptions.map((m) => (
                <option key={m} value={m}>
                  {m}
                </option>
              ))}
            </Select>
          )}
          <ExportCsvLink
            type="invoices"
            label={t("filters.export")}
            basePath="/api/owner/exports"
            query={{
              status: tab === "all" ? undefined : tab,
              month: month || undefined,
            }}
          />
          <Button
            variant="primary"
            size="sm"
            icon="plus"
            onClick={() => setIssueOpen(true)}
          >
            {t("newInvoice")}
          </Button>
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
                  {isFiltered
                    ? t("emptyFiltered.title")
                    : t("empty.title")}
                </div>
                <div style={{ fontSize: "var(--fs-sm)" }}>
                  {isFiltered ? t("emptyFiltered.body") : t("empty.body")}
                </div>
              </div>
              {isFiltered && (
                <Button variant="secondary" size="sm" onClick={resetFilters}>
                  {t("emptyFiltered.reset")}
                </Button>
              )}
            </div>
          }
        />
        {filtered.length > 0 && (
          <div className="table-foot">
            <span>
              {t("filters.count")} <strong>{filtered.length}</strong>
            </span>
            <Pager page={curPage} pages={pages} setPage={setPage} />
          </div>
        )}
      </div>

      {issueOpen && (
        <IssueInvoiceModal
          members={members}
          onClose={() => setIssueOpen(false)}
        />
      )}
    </div>
  );
}
