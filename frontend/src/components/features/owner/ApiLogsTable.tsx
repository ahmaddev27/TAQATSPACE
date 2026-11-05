"use client";

import { useCallback } from "react";
import { useTranslations } from "next-intl";
import { formatDateTime } from "@/lib/dates";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import {
  DataTable,
  FilterChips,
  Pager,
  type DataTableColumn,
  type FilterChip,
} from "@/components/ui/DataTable";
import { Badge } from "@/components/ui/Badge";
import { Input } from "@/components/ui/Input";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import { usePolling } from "@/lib/hooks/usePolling";
import type { RouterApiLog, RouterApiLogMeta } from "@/lib/types/router";

const FILTER_KEYS = ["from", "to", "response_code", "error"] as const;
const REFRESH_MS = 30_000;

export interface ApiLogsTableProps {
  logs: RouterApiLog[];
  meta: RouterApiLogMeta;
}

function codeTone(code: number): "success" | "warning" | "danger" {
  if (code >= 500) return "danger";
  return code >= 400 ? "warning" : "success";
}

/** The router's API request log: URL-driven filters, server pagination, refreshed every 30 s. */
export function ApiLogsTable({ logs, meta }: ApiLogsTableProps) {
  const t = useTranslations("integration.logs");
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { filters, setFilter, clearFilters } = useUrlFilters(FILTER_KEYS);

  usePolling(() => router.refresh(), REFRESH_MS);

  const setPage = useCallback(
    (page: number) => {
      const next = new URLSearchParams(searchParams.toString());
      next.set("page", String(page));
      router.replace(`${pathname}?${next.toString()}`, { scroll: false });
    },
    [router, pathname, searchParams],
  );

  const chips: FilterChip[] = FILTER_KEYS.filter((key) => filters[key] !== "").map((key) => ({
    key,
    label: `${t(`filter.${key}`)}: ${filters[key]}`,
    onRemove: () => setFilter(key, null),
  }));

  const columns: DataTableColumn<RouterApiLog>[] = [
    {
      id: "when",
      header: t("colTime"),
      cell: (l) => (
        <span className="cell-num ltr">
          {l.created_at ? formatDateTime(l.created_at) : "—"}
        </span>
      ),
    },
    { id: "path", header: t("colPath"), cell: (l) => <code className="ltr">{l.path}</code> },
    {
      id: "code",
      header: t("colCode"),
      cell: (l) => (
        <Badge tone={codeTone(l.response_code)} dot>
          {l.response_code}
        </Badge>
      ),
    },
    { id: "size", header: t("colSize"), num: true, cell: (l) => `${l.body_size} B` },
    { id: "duration", header: t("colDuration"), num: true, cell: (l) => `${l.duration_ms} ms` },
    { id: "error", header: t("colError"), cell: (l) => l.error_message ?? "—" },
  ];

  return (
    <div className="dt">
      <div className="dt-toolbar">
        <div className="dt-tools">
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
            inputMode="numeric"
            value={filters.response_code}
            onChange={(e) => setFilter("response_code", e.target.value.trim() || null)}
            placeholder={t("filter.response_code")}
            aria-label={t("filter.response_code")}
          />
          <Input
            value={filters.error}
            onChange={(e) => setFilter("error", e.target.value || null)}
            placeholder={t("filter.error")}
            aria-label={t("filter.error")}
          />
        </div>
        <div className="spacer" />
        <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
          {t("autoRefresh")}
        </span>
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
          empty={<div className="muted">{t("empty")}</div>}
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
