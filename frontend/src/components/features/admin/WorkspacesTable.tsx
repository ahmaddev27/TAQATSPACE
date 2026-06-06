"use client";

import { useMemo, useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { StatusBadge } from "@/components/ui/Badge";
import { Field } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { Tabs } from "@/components/ui/Tabs";
import { Textarea } from "@/components/ui/Textarea";
import { Icon } from "@/components/ui/Icon";
import {
  DataTable,
  Pager,
  sortRows,
  type DataTableColumn,
  type SortState,
} from "@/components/ui/DataTable";
import { useToast } from "@/components/providers/ToastProvider";
import { updateWorkspaceStatus } from "@/lib/actions/admin";
import type { Workspace, WorkspaceStatus } from "@/lib/types";
import { adminDate } from "./format";

type StatusTab = "all" | WorkspaceStatus;

const TABS: StatusTab[] = ["all", "pending", "active", "suspended", "rejected"];
const PAGE_SIZE = 10;

export interface WorkspacesTableProps {
  workspaces: Workspace[];
}

/** Super-admin workspace moderation: filter, search, approve/suspend/reactivate. */
export function WorkspacesTable({ workspaces }: WorkspacesTableProps) {
  const t = useTranslations("admin.workspaces");
  const locale = useLocale();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [tab, setTab] = useState<StatusTab>("all");
  const [query, setQuery] = useState("");
  const [sort, setSort] = useState<SortState | null>({ key: "created", dir: "desc" });
  const [page, setPage] = useState(1);
  const [suspendTarget, setSuspendTarget] = useState<Workspace | null>(null);
  const [reason, setReason] = useState("");

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let rows = workspaces.filter((w) => tab === "all" || w.status === tab);
    if (q) {
      rows = rows.filter(
        (w) =>
          w.name.toLowerCase().includes(q) ||
          w.city.toLowerCase().includes(q) ||
          (w.owner?.name ?? "").toLowerCase().includes(q),
      );
    }
    return sortRows(rows, sort, {
      name: (w) => w.name,
      owner: (w) => w.owner?.name ?? "",
      city: (w) => w.city,
      status: (w) => w.status,
      created: (w) => w.created_at ?? "",
    });
  }, [workspaces, tab, query, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const runStatus = (
    workspace: Workspace,
    status: WorkspaceStatus,
    note?: string,
  ) => {
    startTransition(async () => {
      const res = await updateWorkspaceStatus(workspace.id, status, note);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.statusUpdated") });
        setSuspendTarget(null);
        setReason("");
      } else {
        toast({
          tone: "err",
          title: t("toast.statusFailed"),
          body: res.message,
        });
      }
    });
  };

  const submitSuspend = () => {
    if (!suspendTarget || reason.trim() === "") return;
    runStatus(suspendTarget, "suspended", reason.trim());
  };

  const columns: DataTableColumn<Workspace>[] = [
    {
      id: "name",
      header: t("colName"),
      sortable: true,
      cell: (w) => <span style={{ fontWeight: 600 }}>{w.name}</span>,
    },
    {
      id: "owner",
      header: t("colOwner"),
      sortable: true,
      cell: (w) => (
        <div>
          <div>{w.owner?.name ?? "—"}</div>
          {w.owner?.email && (
            <div className="muted-3 ltr" style={{ fontSize: "var(--fs-xs)" }}>
              {w.owner.email}
            </div>
          )}
        </div>
      ),
    },
    {
      id: "city",
      header: t("colCity"),
      sortable: true,
      cell: (w) => <span className="muted">{w.city}</span>,
    },
    {
      id: "status",
      header: t("colStatus"),
      sortable: true,
      cell: (w) => <StatusBadge status={w.status} locale={locale} />,
    },
    {
      id: "created",
      header: t("colCreated"),
      num: true,
      sortable: true,
      cell: (w) => <span className="cell-num ltr">{adminDate(w.created_at)}</span>,
    },
    {
      id: "actions",
      header: "",
      cell: (w) => (
        <div className="row-actions">
          {(w.status === "pending" || w.status === "suspended" || w.status === "rejected") && (
            <Button
              variant="primary"
              size="sm"
              icon="check"
              loading={pending}
              onClick={() =>
                runStatus(w, "active")
              }
            >
              {w.status === "pending" ? t("approve") : t("reactivate")}
            </Button>
          )}
          {(w.status === "active" || w.status === "pending") && (
            <Button
              variant="danger"
              size="sm"
              icon="x"
              loading={pending}
              onClick={() => {
                setReason("");
                setSuspendTarget(w);
              }}
            >
              {t("suspend")}
            </Button>
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
          rowKey={(w) => w.id}
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

      {suspendTarget && (
        <Modal
          title={t("suspendModal.title")}
          icon="alert"
          onClose={() => setSuspendTarget(null)}
          footer={
            <>
              <Button variant="ghost" onClick={() => setSuspendTarget(null)}>
                {t("suspendModal.cancel")}
              </Button>
              <Button
                variant="danger"
                icon="x"
                loading={pending}
                disabled={reason.trim() === ""}
                onClick={submitSuspend}
              >
                {t("suspendModal.confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 12 }}>
            <p className="muted">
              {t("suspendModal.body", { name: suspendTarget.name })}
            </p>
            <Field label={t("suspendModal.reason")}>
              <Textarea
                rows={3}
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={t("suspendModal.reasonPlaceholder")}
              />
            </Field>
          </div>
        </Modal>
      )}
    </div>
  );
}
