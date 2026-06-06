"use client";

import { useMemo, useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { StatusBadge } from "@/components/ui/Badge";
import { Select } from "@/components/ui/Select";
import { Icon } from "@/components/ui/Icon";
import {
  DataTable,
  Pager,
  sortRows,
  type DataTableColumn,
  type SortState,
} from "@/components/ui/DataTable";
import { useToast } from "@/components/providers/ToastProvider";
import { Link } from "@/i18n/navigation";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import { updateUserStatus } from "@/lib/actions/admin";
import type { User, UserRole, UserStatus } from "@/lib/types";
import { adminDate, nameInitial } from "./format";
import { ExportCsvLink } from "./ExportCsvLink";

type RoleFilter = "all" | UserRole;
type StatusFilter = "all" | UserStatus;

const ROLE_FILTERS: RoleFilter[] = [
  "all",
  "freelancer",
  "workspace_owner",
  "admin",
];
const STATUS_FILTERS: StatusFilter[] = [
  "all",
  "active",
  "suspended",
  "pending_verification",
];
const FILTER_KEYS = ["role", "status", "search"] as const;
const PAGE_SIZE = 10;

export interface UsersTableProps {
  users: User[];
}

/** Super-admin user directory: role/status filters, search, activate/suspend. */
export function UsersTable({ users }: UsersTableProps) {
  const t = useTranslations("admin.users");
  const locale = useLocale();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  // Filters live in the URL so they are shareable and readable by the CSV
  // export link below (the download then matches the on-screen view).
  const { filters, setFilter } = useUrlFilters(FILTER_KEYS);
  const role: RoleFilter = (filters.role as RoleFilter) || "all";
  const status: StatusFilter = (filters.status as StatusFilter) || "all";
  const query = filters.search;
  const [sort, setSort] = useState<SortState | null>({ key: "joined", dir: "desc" });
  const [page, setPage] = useState(1);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let rows = users.filter(
      (u) =>
        (role === "all" || u.role === role) &&
        (status === "all" || u.status === status),
    );
    if (q) {
      rows = rows.filter(
        (u) =>
          u.name.toLowerCase().includes(q) ||
          u.email.toLowerCase().includes(q),
      );
    }
    return sortRows(rows, sort, {
      name: (u) => u.name,
      email: (u) => u.email,
      role: (u) => u.role,
      status: (u) => u.status,
      joined: (u) => u.created_at ?? "",
    });
  }, [users, role, status, query, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const runStatus = (user: User, next: UserStatus) => {
    startTransition(async () => {
      const res = await updateUserStatus(String(user.id), next);
      if (res.ok) {
        toast({
          tone: "ok",
          title:
            next === "suspended" ? t("toast.suspended") : t("toast.activated"),
        });
      } else {
        toast({ tone: "err", title: t("toast.statusFailed"), body: res.message });
      }
    });
  };

  const columns: DataTableColumn<User>[] = [
    {
      id: "name",
      header: t("colName"),
      sortable: true,
      cell: (u) => (
        <div className="row" style={{ gap: 10 }}>
          <Avatar initial={nameInitial(u.name)} round />
          <span style={{ fontWeight: 600 }}>{u.name}</span>
        </div>
      ),
    },
    {
      id: "email",
      header: t("colEmail"),
      sortable: true,
      cell: (u) => (
        <span className="muted-3 ltr" style={{ fontSize: "var(--fs-sm)" }}>
          {u.email}
        </span>
      ),
    },
    {
      id: "role",
      header: t("colRole"),
      sortable: true,
      cell: (u) => <span>{t(`role.${u.role}`)}</span>,
    },
    {
      id: "status",
      header: t("colStatus"),
      sortable: true,
      cell: (u) => <StatusBadge status={u.status} locale={locale} />,
    },
    {
      id: "joined",
      header: t("colJoined"),
      num: true,
      sortable: true,
      cell: (u) => <span className="cell-num ltr">{adminDate(u.created_at)}</span>,
    },
    {
      id: "actions",
      header: "",
      cell: (u) => (
        <div className="row-actions">
          <Link
            href={`/admin/users/${u.id}`}
            className="icon-btn"
            aria-label={t("viewDetails")}
            title={t("viewDetails")}
          >
            <Icon name="eye" />
          </Link>
          {u.status !== "active" && (
            <Button
              variant="primary"
              size="sm"
              icon="check"
              loading={pending}
              onClick={() => runStatus(u, "active")}
            >
              {t("activate")}
            </Button>
          )}
          {u.status !== "suspended" && (
            <Button
              variant="danger"
              size="sm"
              icon="x"
              loading={pending}
              onClick={() => runStatus(u, "suspended")}
            >
              {t("suspend")}
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="dt" style={{ gap: 18 }}>
      <div className="dt-toolbar">
        <div className="dt-tools">
          <Select
            value={role}
            onChange={(e) => {
              setFilter("role", e.target.value === "all" ? null : e.target.value);
              setPage(1);
            }}
            aria-label={t("filterRole")}
          >
            {ROLE_FILTERS.map((r) => (
              <option key={r} value={r}>
                {r === "all" ? t("allRoles") : t(`role.${r}`)}
              </option>
            ))}
          </Select>
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
        <ExportCsvLink
          type="users"
          label={t("exportCsv")}
          query={{
            role: role === "all" ? undefined : role,
            status: status === "all" ? undefined : status,
            search: query || undefined,
          }}
        />
      </div>

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={pageRows}
          rowKey={(u) => String(u.id)}
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
