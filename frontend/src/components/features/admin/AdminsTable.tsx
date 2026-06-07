"use client";

import { useMemo, useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { Badge, StatusBadge } from "@/components/ui/Badge";
import { Select } from "@/components/ui/Select";
import { Icon } from "@/components/ui/Icon";
import {
  DataTable,
  Pager,
  sortRows,
  type DataTableColumn,
  type SortState,
} from "@/components/ui/DataTable";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { useToast } from "@/components/providers/ToastProvider";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import { deactivateAdmin } from "@/lib/actions/admin";
import type { ManagedAdmin } from "@/lib/api/admin";
import type { UserStatus } from "@/lib/types";
import { adminDate, nameInitial } from "./format";
import { AdminFormModal } from "./AdminFormModal";

type StatusFilter = "all" | UserStatus;

const STATUS_FILTERS: StatusFilter[] = [
  "all",
  "active",
  "suspended",
];
const FILTER_KEYS = ["status", "search"] as const;
const PAGE_SIZE = 10;

type Editing = { mode: "create" } | { mode: "edit"; admin: ManagedAdmin } | null;

export interface AdminsTableProps {
  admins: ManagedAdmin[];
  /** Signed-in super-admin's id, to disable self-destructive actions. */
  currentUserId: string;
}

/** Super-admin directory of staff accounts: filter, create, edit, deactivate. */
export function AdminsTable({ admins, currentUserId }: AdminsTableProps) {
  const t = useTranslations("admin.admins");
  const locale = useLocale();
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();

  const { filters, setFilter } = useUrlFilters(FILTER_KEYS);
  const status: StatusFilter = (filters.status as StatusFilter) || "all";
  const query = filters.search;
  const [sort, setSort] = useState<SortState | null>({ key: "joined", dir: "desc" });
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<Editing>(null);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let rows = admins.filter((a) => status === "all" || a.status === status);
    if (q) {
      rows = rows.filter(
        (a) =>
          a.name.toLowerCase().includes(q) ||
          a.email.toLowerCase().includes(q),
      );
    }
    return sortRows(rows, sort, {
      name: (a) => a.name,
      email: (a) => a.email,
      status: (a) => a.status,
      joined: (a) => a.created_at ?? "",
    });
  }, [admins, status, query, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const handleDeactivate = async (admin: ManagedAdmin) => {
    const ok = await confirm({
      title: t("deactivateTitle"),
      message: t("deactivateConfirm", { name: admin.name }),
      confirmLabel: t("deactivate"),
      tone: "danger",
      icon: "x",
    });
    if (!ok) return;
    startTransition(async () => {
      const res = await deactivateAdmin(admin.id);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.deactivated") });
      } else {
        toast({ tone: "err", title: t("toast.deactivateFailed"), body: res.message });
      }
    });
  };

  const columns: DataTableColumn<ManagedAdmin>[] = [
    {
      id: "name",
      header: t("colName"),
      sortable: true,
      cell: (a) => (
        <div className="row" style={{ gap: 10 }}>
          <Avatar initial={nameInitial(a.name)} round />
          <span style={{ fontWeight: 600 }}>{a.name}</span>
        </div>
      ),
    },
    {
      id: "email",
      header: t("colEmail"),
      sortable: true,
      cell: (a) => (
        <span className="muted-3 ltr" style={{ fontSize: "var(--fs-sm)" }}>
          {a.email}
        </span>
      ),
    },
    {
      id: "roles",
      header: t("colRoles"),
      cell: (a) => (
        <div className="row" style={{ gap: 6, flexWrap: "wrap" }}>
          <Badge tone={a.is_super_admin ? "info" : "neutral"}>
            {a.is_super_admin ? t("role.super_admin") : t("role.admin")}
          </Badge>
          <span className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("permissionCount", { count: a.permissions.length })}
          </span>
        </div>
      ),
    },
    {
      id: "status",
      header: t("colStatus"),
      sortable: true,
      cell: (a) => <StatusBadge status={a.status} locale={locale} />,
    },
    {
      id: "joined",
      header: t("colJoined"),
      num: true,
      sortable: true,
      cell: (a) => <span className="cell-num ltr">{adminDate(a.created_at)}</span>,
    },
    {
      id: "actions",
      header: "",
      cell: (a) => {
        const isSelf = a.id === currentUserId;
        return (
          <div className="row-actions">
            <Button
              variant="secondary"
              size="sm"
              icon="edit"
              onClick={() => setEditing({ mode: "edit", admin: a })}
              aria-label={t("edit")}
              title={t("edit")}
            />
            {!isSelf && a.status === "active" && (
              <Button
                variant="danger"
                size="sm"
                icon="x"
                loading={pending}
                onClick={() => handleDeactivate(a)}
              >
                {t("deactivate")}
              </Button>
            )}
          </div>
        );
      },
    },
  ];

  return (
    <div className="dt" style={{ gap: 18 }}>
      <div className="dt-toolbar">
        <div className="dt-tools">
          <Select
            value={status}
            onChange={(e) => {
              setFilter("status", e.target.value === "all" ? null : e.target.value);
              setPage(1);
            }}
            aria-label={t("filterStatus")}
          >
            {STATUS_FILTERS.map((s) => (
              <option key={s} value={s}>
                {s === "all" ? t("allStatuses") : t(`statusOption.${s}`)}
              </option>
            ))}
          </Select>
        </div>
        <div className="spacer" />
        <div className="dt-search input-icon">
          <Icon name="search" />
          <input
            className="input"
            value={query}
            onChange={(e) => {
              setFilter("search", e.target.value);
              setPage(1);
            }}
            placeholder={t("searchPlaceholder")}
          />
        </div>
        <Button
          variant="primary"
          icon="plus"
          onClick={() => setEditing({ mode: "create" })}
        >
          {t("addAdmin")}
        </Button>
      </div>

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={pageRows}
          rowKey={(a) => a.id}
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

      {editing && (
        <AdminFormModal
          admin={editing.mode === "edit" ? editing.admin : null}
          isSelf={editing.mode === "edit" && editing.admin.id === currentUserId}
          onClose={() => setEditing(null)}
        />
      )}
    </div>
  );
}
