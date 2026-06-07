"use client";

import { useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { StatusBadge } from "@/components/ui/Badge";
import { Input } from "@/components/ui/Input";
import { Tabs } from "@/components/ui/Tabs";
import { Icon } from "@/components/ui/Icon";
import {
  DataTable,
  Pager,
  sortRows,
  type DataTableColumn,
  type SortState,
} from "@/components/ui/DataTable";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import type { AdminSubscription } from "@/lib/api/admin";
import type { SubscriptionStatus } from "@/lib/types";
import { invoiceMoney } from "@/components/features/invoices/format";
import { adminDate } from "./format";
import { ExportCsvLink } from "./ExportCsvLink";

type StatusTab = "all" | SubscriptionStatus;

const TABS: StatusTab[] = [
  "all",
  "active",
  "pending",
  "suspended",
  "expired",
  "cancelled",
];
// `member` is the backend list search param; `start_date` drives the range.
const FILTER_KEYS = ["status", "member", "date_from", "date_to"] as const;
const PAGE_SIZE = 10;

export interface SubscriptionsTableProps {
  subscriptions: AdminSubscription[];
}

/** Read-only super-admin subscription tracking: status tabs + search. */
export function SubscriptionsTable({ subscriptions }: SubscriptionsTableProps) {
  const t = useTranslations("admin.subscriptions");
  const tc = useTranslations();
  const locale = useLocale();

  // Filters live in the URL so they are shareable and readable by the CSV
  // export link below (the download then matches the on-screen view).
  const { filters, setFilter } = useUrlFilters(FILTER_KEYS);
  const tab: StatusTab = (filters.status as StatusTab) || "all";
  const query = filters.member;
  const dateFrom = filters.date_from;
  const dateTo = filters.date_to;
  const [sort, setSort] = useState<SortState | null>({ key: "start", dir: "desc" });
  const [page, setPage] = useState(1);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let rows = subscriptions.filter((s) => tab === "all" || s.status === tab);
    if (q) {
      rows = rows.filter(
        (s) =>
          (s.member?.name ?? "").toLowerCase().includes(q) ||
          (s.member?.email ?? "").toLowerCase().includes(q) ||
          (s.workspace?.name ?? "").toLowerCase().includes(q),
      );
    }
    if (dateFrom) {
      rows = rows.filter((s) => (s.start_date ?? "") >= dateFrom);
    }
    if (dateTo) {
      rows = rows.filter((s) => (s.start_date ?? "") <= dateTo);
    }
    return sortRows(rows, sort, {
      member: (s) => s.member?.name ?? "",
      workspace: (s) => s.workspace?.name ?? "",
      plan: (s) => s.plan_type,
      price: (s) => Number(s.monthly_price),
      start: (s) => s.start_date ?? "",
      status: (s) => s.status,
    });
  }, [subscriptions, tab, query, dateFrom, dateTo, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const columns: DataTableColumn<AdminSubscription>[] = [
    {
      id: "member",
      header: t("colMember"),
      sortable: true,
      cell: (s) => (
        <div>
          <div style={{ fontWeight: 600 }}>{s.member?.name ?? "—"}</div>
          {s.member?.email && (
            <div className="muted-3 ltr" style={{ fontSize: "var(--fs-xs)" }}>
              {s.member.email}
            </div>
          )}
        </div>
      ),
    },
    {
      id: "workspace",
      header: t("colWorkspace"),
      sortable: true,
      cell: (s) => <span className="muted">{s.workspace?.name ?? "—"}</span>,
    },
    {
      id: "plan",
      header: t("colPlan"),
      sortable: true,
      cell: (s) => <span>{tc(`planType.${s.plan_type}` as never)}</span>,
    },
    {
      id: "price",
      header: t("colPrice"),
      num: true,
      sortable: true,
      cell: (s) => (
        <span className="cell-num" style={{ fontWeight: 600 }}>
          {invoiceMoney(s.monthly_price)}
        </span>
      ),
    },
    {
      id: "start",
      header: t("colStart"),
      num: true,
      sortable: true,
      cell: (s) => <span className="cell-num ltr">{adminDate(s.start_date)}</span>,
    },
    {
      id: "end",
      header: t("colEnd"),
      num: true,
      cell: (s) => <span className="cell-num ltr">{adminDate(s.end_date)}</span>,
    },
    {
      id: "status",
      header: t("colStatus"),
      sortable: true,
      cell: (s) => <StatusBadge status={s.status} locale={locale} />,
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
            setFilter("status", v === "all" ? null : v);
            setPage(1);
          }}
        />
        <div className="spacer" />
        <div className="dt-tools">
          <Input
            className="ltr"
            type="date"
            value={dateFrom}
            onChange={(e) => {
              setFilter("date_from", e.target.value);
              setPage(1);
            }}
            aria-label={t("dateFrom")}
            title={t("dateFrom")}
          />
          <Input
            className="ltr"
            type="date"
            value={dateTo}
            onChange={(e) => {
              setFilter("date_to", e.target.value);
              setPage(1);
            }}
            aria-label={t("dateTo")}
            title={t("dateTo")}
          />
        </div>
        <div className="dt-search input-icon">
          <Icon name="search" />
          <input
            className="input"
            value={query}
            onChange={(e) => {
              setFilter("member", e.target.value);
              setPage(1);
            }}
            placeholder={t("searchPlaceholder")}
          />
        </div>
        <ExportCsvLink
          type="subscriptions"
          label={t("exportCsv")}
          query={{
            status: tab === "all" ? undefined : tab,
            search: query || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
          }}
        />
      </div>

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={pageRows}
          rowKey={(s) => s.id}
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
    </div>
  );
}
