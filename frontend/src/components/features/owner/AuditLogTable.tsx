"use client";

import { useCallback } from "react";
import { useTranslations } from "next-intl";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import {
  DataTable,
  FilterChips,
  Pager,
  type DataTableColumn,
  type FilterChip,
} from "@/components/ui/DataTable";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import type { AuditLogEntry, AuditLogMeta } from "@/lib/types/audit";

const FILTER_KEYS = ["actor_id", "action", "from", "to", "subject_type", "subject_id", "reason"] as const;
const FINANCE_ACTIONS = "finance.*";

export interface AuditLogTableProps {
  logs: AuditLogEntry[];
  meta: AuditLogMeta;
}

function formatWhen(iso: string | null): string {
  return iso ? new Date(iso).toLocaleString() : "—";
}

/** Owner audit log: URL-driven filters, server-side pagination, read-only. */
export function AuditLogTable({ logs, meta }: AuditLogTableProps) {
  const t = useTranslations("audit");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { filters, setFilter, clearFilters } = useUrlFilters(FILTER_KEYS);

  const setPage = useCallback(
    (page: number) => {
      const next = new URLSearchParams(searchParams.toString());
      next.set("page", String(page));
      router.replace(`${pathname}?${next.toString()}`, { scroll: false });
    },
    [router, pathname, searchParams],
  );

  const financeOnly = filters.action === FINANCE_ACTIONS;

  const chips: FilterChip[] = FILTER_KEYS.filter((key) => filters[key] !== "").map(
    (key) => ({
      key,
      label: `${t(`filter.${key}`)}: ${filters[key]}`,
      onRemove: () => setFilter(key, null),
    }),
  );

  const columns: DataTableColumn<AuditLogEntry>[] = [
    {
      id: "when",
      header: t("colWhen"),
      cell: (l) => <span className="cell-num ltr">{formatWhen(l.created_at)}</span>,
    },
    {
      id: "actor",
      header: t("colActor"),
      cell: (l) => (
        <span style={{ fontWeight: 600 }}>
          {l.actor?.name ?? t(`actorType.${l.actor_type}`)}
        </span>
      ),
    },
    {
      id: "action",
      header: t("colAction"),
      cell: (l) => <code className="ltr">{l.action}</code>,
    },
    {
      id: "subject",
      header: t("colSubject"),
      cell: (l) => (
        <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
          {l.subject_type ? `${l.subject_type.split("\\").pop()} ${l.subject_id ?? ""}` : "—"}
        </span>
      ),
    },
    {
      id: "reason",
      header: t("colReason"),
      cell: (l) => <span className="muted-3">{l.reason || "—"}</span>,
    },
    {
      id: "ip",
      header: t("colIp"),
      cell: (l) => <span className="cell-num ltr">{l.ip ?? "—"}</span>,
    },
  ];

  return (
    <div className="dt">
      <div className="dt-toolbar">
        <div className="dt-tools">
          <Input
            value={filters.actor_id}
            onChange={(e) => setFilter("actor_id", e.target.value.trim() || null)}
            placeholder={t("filter.actor_id")}
            aria-label={t("filter.actor_id")}
          />
          <Input
            value={filters.action}
            onChange={(e) => setFilter("action", e.target.value.trim() || null)}
            placeholder={t("filter.action")}
            aria-label={t("filter.action")}
          />
          <Input
            type="date"
            value={filters.from}
            onChange={(e) => setFilter("from", e.target.value || null)}
            aria-label={t("filter.from")}
          />
          <Input
            type="date"
            value={filters.to}
            onChange={(e) => setFilter("to", e.target.value || null)}
            aria-label={t("filter.to")}
          />
          <Input
            value={filters.subject_type}
            onChange={(e) => setFilter("subject_type", e.target.value.trim() || null)}
            placeholder={t("filter.subject_type")}
            aria-label={t("filter.subject_type")}
          />
          <Input
            value={filters.subject_id}
            onChange={(e) => setFilter("subject_id", e.target.value.trim() || null)}
            placeholder={t("filter.subject_id")}
            aria-label={t("filter.subject_id")}
          />
          <Input
            value={filters.reason}
            onChange={(e) => setFilter("reason", e.target.value || null)}
            placeholder={t("filter.reason")}
            aria-label={t("filter.reason")}
          />
        </div>
        <div className="spacer" />
        <Button
          variant={financeOnly ? "primary" : "secondary"}
          icon="wallet"
          onClick={() => setFilter("action", financeOnly ? null : FINANCE_ACTIONS)}
        >
          {t("financeOnly")}
        </Button>
      </div>

      {chips.length > 0 && (
        <FilterChips
          chips={chips}
          onClear={clearFilters}
          filtersLabel={t("filters")}
          clearLabel={t("clearAll")}
        />
      )}

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={logs}
          rowKey={(l) => String(l.id)}
          empty={
            <div className="empty-state">
              <div className="st-ico" style={{ width: 48, height: 48 }}>
                <Icon name="shield" />
              </div>
              <div>
                <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                  {t("emptyTitle")}
                </div>
                <div style={{ fontSize: "var(--fs-sm)" }}>{t("emptyBody")}</div>
              </div>
            </div>
          }
        />
        {meta.total > 0 && (
          <div className="table-foot">
            <span>
              {t("count")}: <strong>{meta.total}</strong>
            </span>
            <Pager page={meta.current_page} pages={meta.last_page} setPage={setPage} />
          </div>
        )}
      </div>
    </div>
  );
}
