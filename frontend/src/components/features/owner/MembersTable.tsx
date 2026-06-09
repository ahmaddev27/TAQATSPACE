"use client";

import { useMemo, useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { StatusBadge } from "@/components/ui/Badge";
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
import { useToast } from "@/components/providers/ToastProvider";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import { ExportCsvLink } from "@/components/features/admin/ExportCsvLink";
import { updateMemberStatus } from "@/lib/actions/owner";
import type { Member, SubscriptionStatus } from "@/lib/types";
import { avatarInitial, money, shortDate } from "./format";
import { MemberDrawer } from "./MemberDrawer";

type StatusFilter = "all" | "active" | "suspended" | "pending";

const PAGE_SIZE = 8;
const FILTERS: StatusFilter[] = ["all", "active", "suspended"];
const FILTER_KEYS = ["status", "search"] as const;

export interface MembersTableProps {
  members: Member[];
}

/** Members roster: searchable, filterable, sortable table with a detail drawer. */
export function MembersTable({ members }: MembersTableProps) {
  const t = useTranslations("owner");
  const tc = useTranslations();
  const locale = useLocale();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  // Filters live in the URL so they are shareable and readable by the CSV
  // export link (the download then matches the on-screen view).
  const { filters, setFilter: setUrlFilter, clearFilters } =
    useUrlFilters(FILTER_KEYS);
  const filter: StatusFilter = (filters.status as StatusFilter) || "all";
  const query = filters.search;
  const setFilter = (value: StatusFilter) =>
    setUrlFilter("status", value === "all" ? null : value);
  const setQuery = (value: string) => setUrlFilter("search", value);
  const [sort, setSort] = useState<SortState | null>({ key: "name", dir: "asc" });
  const [page, setPage] = useState(1);
  const [drawer, setDrawer] = useState<Member | null>(null);

  const statusLabel: Record<StatusFilter, string> = {
    all: tc("common.all"),
    active: tc("status.active"),
    suspended: tc("status.suspended"),
    pending: tc("status.pending"),
  };

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let rows = members.filter(
      (m) => filter === "all" || m.status === filter,
    );
    if (q) {
      rows = rows.filter(
        (m) =>
          m.user.name.toLowerCase().includes(q) ||
          m.user.email.toLowerCase().includes(q),
      );
    }
    return sortRows(rows, sort, {
      name: (m) => m.user.name,
      status: (m) => m.status,
      seat: (m) => m.seat_number ?? "",
      plan: (m) => m.plan_type,
      price: (m) => m.monthly_price,
      joined: (m) => m.join_date ?? "",
    });
  }, [members, filter, query, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const chips: FilterChip[] = [];
  if (filter !== "all") {
    chips.push({
      key: "st",
      label: `${t("members.filterStatus")}: ${statusLabel[filter]}`,
      onRemove: () => setFilter("all"),
    });
  }
  if (query) {
    chips.push({
      key: "q",
      label: `${t("members.filterSearch")}: "${query}"`,
      onRemove: () => setQuery(""),
    });
  }
  const clearAll = () => {
    clearFilters();
    setPage(1);
  };

  const handleStatus = (member: Member, status: SubscriptionStatus) => {
    startTransition(async () => {
      const res = await updateMemberStatus(String(member.user.id), status);
      if (res.ok) {
        toast({
          tone: "ok",
          title:
            status === "suspended"
              ? t("members.suspended")
              : t("members.activated"),
        });
        setDrawer(null);
      } else {
        toast({ tone: "err", title: t("members.statusUpdateFailed"), body: res.message });
      }
    });
  };

  const columns: DataTableColumn<Member>[] = [
    {
      id: "name",
      header: t("members.colMember"),
      sortable: true,
      cell: (m) => (
        <div className="row" style={{ gap: 10 }}>
          <Avatar initial={avatarInitial(m.user.name)} src={m.user.avatar} alt={m.user.name} round />
          <div>
            <div style={{ fontWeight: 600 }}>{m.user.name}</div>
            <div className="muted-3 ltr" style={{ fontSize: "var(--fs-xs)" }}>
              {m.user.email}
            </div>
          </div>
        </div>
      ),
    },
    {
      id: "spec",
      header: t("members.colSpecialty"),
      cell: (m) => <span className="muted">{m.user.specialty ?? "—"}</span>,
    },
    {
      id: "seat",
      header: t("members.colSeat"),
      num: true,
      sortable: true,
      cell: (m) => (
        <span className="cell-num">{m.seat_number ?? t("members.noSeat")}</span>
      ),
    },
    {
      id: "plan",
      header: t("members.colPlan"),
      sortable: true,
      cell: (m) => <span>{tc(`planType.${m.plan_type}` as never)}</span>,
    },
    {
      id: "price",
      header: t("members.colPrice"),
      num: true,
      sortable: true,
      cell: (m) => (
        <span className="cell-num" style={{ fontWeight: 600 }}>
          {money(m.monthly_price)}
        </span>
      ),
    },
    {
      id: "joined",
      header: t("members.colJoined"),
      num: true,
      sortable: true,
      cell: (m) => <span className="cell-num ltr">{shortDate(m.join_date)}</span>,
    },
    {
      id: "status",
      header: t("members.colStatus"),
      sortable: true,
      cell: (m) => <StatusBadge status={m.status} locale={locale} />,
    },
    {
      id: "actions",
      header: "",
      cell: (m) => (
        <div className="row-actions">
          <button
            type="button"
            className="icon-btn"
            onClick={(e) => {
              e.stopPropagation();
              setDrawer(m);
            }}
            aria-label={t("members.drawerTitle")}
          >
            <Icon name="eye" />
          </button>
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
            placeholder={t("members.searchPlaceholder")}
          />
        </div>
        <ExportCsvLink
          type="members"
          label={t("members.export")}
          basePath="/api/owner/exports"
          query={{
            status: filter === "all" ? undefined : filter,
            search: query || undefined,
          }}
        />
      </div>

      {chips.length > 0 && (
        <FilterChips
          chips={chips}
          onClear={clearAll}
          filtersLabel={t("members.filters")}
          clearLabel={t("members.clearAll")}
        />
      )}

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={pageRows}
          rowKey={(m) => m.subscription_id}
          sort={sort}
          setSort={setSort}
          onRowClick={(m) => setDrawer(m)}
          empty={
            <div className="empty-state">
              <div>
                <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                  {t("members.emptyTitle")}
                </div>
                <div style={{ fontSize: "var(--fs-sm)" }}>
                  {t("members.emptyBody")}
                </div>
              </div>
              <Button variant="secondary" size="sm" onClick={clearAll}>
                {t("members.reset")}
              </Button>
            </div>
          }
        />
        {filtered.length > 0 && (
          <div className="table-foot">
            <span>
              {t("members.colMember")}: <strong>{filtered.length}</strong>
            </span>
            <Pager page={curPage} pages={pages} setPage={setPage} />
          </div>
        )}
      </div>

      {drawer && (
        <MemberDrawer
          member={drawer}
          locale={locale}
          pending={pending}
          onClose={() => setDrawer(null)}
          onSuspend={() => handleStatus(drawer, "suspended")}
          onActivate={() => handleStatus(drawer, "active")}
        />
      )}
    </div>
  );
}
