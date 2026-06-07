"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import {
  DataTable,
  FilterChips,
  Pager,
  sortRows,
  type DataTableColumn,
  type FilterChip,
  type SortState,
} from "@/components/ui/DataTable";
import { Icon } from "@/components/ui/Icon";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { useToast } from "@/components/providers/ToastProvider";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import { cancelSubscription, renewSubscription } from "@/lib/actions/owner";
import type { OwnerSubscription } from "@/lib/api/ownerSubscriptions";
import { avatarInitial, money, shortDate } from "./format";

/* -------------------------------------------------------------------------- */
/*  Derived status (status + period → display state)                          */
/* -------------------------------------------------------------------------- */

type DisplayStatus = "active" | "expiring" | "expired" | "cancelled" | "pending";

/** Days from today until `end` (negative once it has passed); null when no end. */
function daysUntil(end: string | null): number | null {
  if (!end) return null;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(end);
  if (Number.isNaN(target.getTime())) return null;
  target.setHours(0, 0, 0, 0);
  return Math.round((target.getTime() - today.getTime()) / 86_400_000);
}

const EXPIRING_SOON_DAYS = 7;

/**
 * Map a subscription to its display status. Cancelled/expired/pending pass
 * through; an active subscription whose end date is within a week is surfaced
 * as "expiring" (and one already past its end date as "expired") so the owner
 * can act before the member lapses.
 */
function displayStatus(sub: OwnerSubscription): DisplayStatus {
  if (sub.status === "cancelled" || sub.status === "suspended") return "cancelled";
  if (sub.status === "expired") return "expired";
  if (sub.status === "pending") return "pending";

  const days = daysUntil(sub.end_date);
  if (days !== null) {
    if (days < 0) return "expired";
    if (days <= EXPIRING_SOON_DAYS) return "expiring";
  }
  return "active";
}

const STATUS_CLASS: Record<DisplayStatus, string> = {
  active: "badge-success",
  expiring: "badge-warning",
  expired: "badge-neutral",
  cancelled: "badge-danger",
  pending: "badge-warning",
};

type StatusFilter = "all" | DisplayStatus;
const FILTERS: StatusFilter[] = [
  "all",
  "active",
  "expiring",
  "expired",
  "cancelled",
];

const FILTER_KEYS = ["status", "search"] as const;
const PAGE_SIZE = 8;

export interface SubscriptionsTableProps {
  subscriptions: OwnerSubscription[];
}

/** Owner subscriptions: searchable, filterable, sortable table with renew/cancel. */
export function SubscriptionsTable({ subscriptions }: SubscriptionsTableProps) {
  const t = useTranslations("owner.subscriptions");
  const tc = useTranslations();
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [busyId, setBusyId] = useState<string | null>(null);

  // Filters live in the URL so the view is shareable and survives a refresh.
  const { filters, setFilter: setUrlFilter, clearFilters } =
    useUrlFilters(FILTER_KEYS);
  const filter: StatusFilter = (filters.status as StatusFilter) || "all";
  const query = filters.search;
  const setFilter = (value: StatusFilter) =>
    setUrlFilter("status", value === "all" ? null : value);
  const setQuery = (value: string) => setUrlFilter("search", value);
  const [sort, setSort] = useState<SortState | null>({ key: "end", dir: "asc" });
  const [page, setPage] = useState(1);

  const statusLabel: Record<StatusFilter, string> = {
    all: tc("common.all"),
    active: t("statusActive"),
    expiring: t("statusExpiring"),
    expired: t("statusExpired"),
    cancelled: t("statusCancelled"),
    pending: tc("status.pending"),
  };

  // Precompute each row's display status once for filtering, sorting, badges.
  const rowsWithStatus = useMemo(
    () => subscriptions.map((s) => ({ sub: s, display: displayStatus(s) })),
    [subscriptions],
  );

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let rows = rowsWithStatus.filter(
      (r) => filter === "all" || r.display === filter,
    );
    if (q) {
      rows = rows.filter(
        (r) =>
          r.sub.member.name.toLowerCase().includes(q) ||
          r.sub.member.email.toLowerCase().includes(q),
      );
    }
    return sortRows(rows, sort, {
      member: (r) => r.sub.member.name,
      plan: (r) => r.sub.plan_type,
      price: (r) => Number(r.sub.monthly_price),
      seat: (r) => r.sub.seat_number ?? "",
      start: (r) => r.sub.start_date ?? "",
      end: (r) => r.sub.end_date ?? "",
      status: (r) => r.display,
    });
  }, [rowsWithStatus, filter, query, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const chips: FilterChip[] = [];
  if (filter !== "all") {
    chips.push({
      key: "st",
      label: `${t("filterStatus")}: ${statusLabel[filter]}`,
      onRemove: () => setFilter("all"),
    });
  }
  if (query) {
    chips.push({
      key: "q",
      label: `${t("filterSearch")}: "${query}"`,
      onRemove: () => setQuery(""),
    });
  }
  const clearAll = () => {
    clearFilters();
    setPage(1);
  };

  const handleRenew = (sub: OwnerSubscription) => {
    setBusyId(sub.id);
    startTransition(async () => {
      const res = await renewSubscription(sub.id);
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t("renewed") });
      } else {
        toast({ tone: "err", title: t("renewFailed"), body: res.message });
      }
    });
  };

  const askCancel = async (sub: OwnerSubscription) => {
    const ok = await confirm({
      title: t("cancel"),
      message: t("cancelConfirm"),
      confirmLabel: t("cancel"),
      tone: "danger",
      icon: "x",
    });
    if (!ok) return;
    setBusyId(sub.id);
    startTransition(async () => {
      const res = await cancelSubscription(sub.id);
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t("cancelled") });
      } else {
        toast({ tone: "err", title: t("cancelFailed"), body: res.message });
      }
    });
  };

  const columns: DataTableColumn<(typeof rowsWithStatus)[number]>[] = [
    {
      id: "member",
      header: t("colMember"),
      sortable: true,
      cell: ({ sub }) => (
        <div className="row" style={{ gap: 10 }}>
          <Avatar initial={avatarInitial(sub.member.name)} round />
          <div>
            <div style={{ fontWeight: 600 }}>{sub.member.name}</div>
            <div className="muted-3 ltr" style={{ fontSize: "var(--fs-xs)" }}>
              {sub.member.email}
            </div>
          </div>
        </div>
      ),
    },
    {
      id: "plan",
      header: t("colPlan"),
      sortable: true,
      cell: ({ sub }) => <span>{tc(`planType.${sub.plan_type}` as never)}</span>,
    },
    {
      id: "price",
      header: t("colPrice"),
      num: true,
      sortable: true,
      cell: ({ sub }) => (
        <span className="cell-num" style={{ fontWeight: 600 }}>
          {money(sub.monthly_price)}
        </span>
      ),
    },
    {
      id: "seat",
      header: t("colSeat"),
      num: true,
      sortable: true,
      cell: ({ sub }) => (
        <span className="cell-num">{sub.seat_number ?? t("noSeat")}</span>
      ),
    },
    {
      id: "start",
      header: t("colStart"),
      num: true,
      sortable: true,
      cell: ({ sub }) => (
        <span className="cell-num ltr">{shortDate(sub.start_date)}</span>
      ),
    },
    {
      id: "end",
      header: t("colEnd"),
      num: true,
      sortable: true,
      cell: ({ sub }) => (
        <span className="cell-num ltr">{shortDate(sub.end_date)}</span>
      ),
    },
    {
      id: "status",
      header: t("colStatus"),
      sortable: true,
      cell: ({ display }) => (
        <span className={`badge ${STATUS_CLASS[display]}`}>
          <span className="dot" />
          {statusLabel[display]}
        </span>
      ),
    },
    {
      id: "actions",
      header: "",
      cell: ({ sub, display }) => (
        <div className="row-actions" style={{ gap: 6 }}>
          {display !== "cancelled" && (
            <Button
              variant="secondary"
              size="sm"
              icon="refresh"
              loading={pending && busyId === sub.id}
              onClick={(e) => {
                e.stopPropagation();
                handleRenew(sub);
              }}
            >
              {t("renew")}
            </Button>
          )}
          {display !== "cancelled" && (
            <Button
              variant="ghost"
              size="sm"
              icon="x"
              loading={pending && busyId === sub.id}
              onClick={(e) => {
                e.stopPropagation();
                void askCancel(sub);
              }}
              aria-label={t("cancel")}
            />
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="dt">
      <div className="dt-toolbar">
        <div className="seg">
          {FILTERS.map((k) => (
            <button
              key={k}
              type="button"
              className={filter === k ? "active" : ""}
              onClick={() => {
                setFilter(k);
                setPage(1);
              }}
            >
              {statusLabel[k]}
            </button>
          ))}
        </div>
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

      {chips.length > 0 && (
        <FilterChips
          chips={chips}
          onClear={clearAll}
          filtersLabel={t("filters")}
          clearLabel={t("clearAll")}
        />
      )}

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={pageRows}
          rowKey={({ sub }) => sub.id}
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
              <Button variant="secondary" size="sm" onClick={clearAll}>
                {t("reset")}
              </Button>
            </div>
          }
        />
        {filtered.length > 0 && (
          <div className="table-foot">
            <span>
              {t("count")}: <strong>{filtered.length}</strong>
            </span>
            <Pager page={curPage} pages={pages} setPage={setPage} />
          </div>
        )}
      </div>
    </div>
  );
}
