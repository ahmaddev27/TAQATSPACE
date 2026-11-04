"use client";

import { useLocale, useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import type { RouterCommand, RouterCommandStatus } from "@/lib/types/router";

const TONES: Record<RouterCommandStatus, "neutral" | "success" | "warning" | "danger" | "info"> = {
  requested: "neutral",
  delivered: "info",
  acknowledged: "success",
  failed: "danger",
  expired: "warning",
};

export interface CommandsTableProps {
  commands: RouterCommand[];
}

/** Recent platform-to-router commands and where each one stands. */
export function CommandsTable({ commands }: CommandsTableProps) {
  const t = useTranslations("integration.commands");
  const locale = useLocale();

  const when = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale, { dateStyle: "short", timeStyle: "short" }) : "—";

  const columns: DataTableColumn<RouterCommand>[] = [
    {
      id: "verb",
      header: t("colCommand"),
      cell: (c) => <span className="ltr">{c.verb}</span>,
    },
    {
      id: "status",
      header: t("colStatus"),
      cell: (c) => (
        <Badge tone={TONES[c.status]} dot>
          {t(`status.${c.status}`)}
        </Badge>
      ),
    },
    { id: "created", header: t("colQueued"), cell: (c) => when(c.created_at) },
    { id: "ack", header: t("colAcknowledged"), cell: (c) => when(c.acknowledged_at) },
    { id: "error", header: t("colError"), cell: (c) => c.error ?? "—" },
  ];

  return (
    <div className="table-wrap">
      <DataTable
        columns={columns}
        rows={commands}
        rowKey={(c) => c.id}
        empty={<div className="muted">{t("empty")}</div>}
      />
    </div>
  );
}
