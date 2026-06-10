"use client";

import { useMemo, useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Badge, StatusBadge } from "@/components/ui/Badge";
import { Field } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { Tabs } from "@/components/ui/Tabs";
import { Textarea } from "@/components/ui/Textarea";
import { Icon } from "@/components/ui/Icon";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import {
  DataTable,
  Pager,
  sortRows,
  type DataTableColumn,
  type SortState,
} from "@/components/ui/DataTable";
import { useToast } from "@/components/providers/ToastProvider";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import { setWorkspacePublished, updateWorkspaceStatus } from "@/lib/actions/admin";
import type { Workspace, WorkspaceStatus } from "@/lib/types";
import { adminDate } from "./format";
import { ExportCsvLink } from "./ExportCsvLink";

type StatusTab = "all" | WorkspaceStatus;

const TABS: StatusTab[] = ["all", "pending", "active", "suspended", "rejected"];
// Only `status` is honoured by the backend workspace export; `search` is a
// client-only convenience and is intentionally NOT forwarded to the download.
const FILTER_KEYS = ["status", "search"] as const;
const PAGE_SIZE = 10;

export interface WorkspacesTableProps {
  workspaces: Workspace[];
}

/** Super-admin workspace moderation: filter, search, approve/suspend/reactivate. */
export function WorkspacesTable({ workspaces }: WorkspacesTableProps) {
  const t = useTranslations("admin.workspaces");
  const locale = useLocale();
  const { toast } = useToast();
  const confirm = useConfirm();
  const [, startTransition] = useTransition();
  // Track the specific (row + action) in flight so ONLY the clicked button
  // spins. A row exposes several action buttons, so a row-only flag would spin
  // all of them — e.g. clicking "unpublish" must not spin "suspend".
  const [busy, setBusy] = useState<string | null>(null);
  const busyKey = (id: string, action: string) => `${id}:${action}`;

  // Filters live in the URL so they are shareable and readable by the CSV
  // export link below (the download then matches the on-screen view).
  const { filters, setFilter } = useUrlFilters(FILTER_KEYS);
  const tab: StatusTab = (filters.status as StatusTab) || "all";
  const query = filters.search;
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
    // Activating and suspending are distinct buttons, so they key separately.
    const action = status === "active" ? "activate" : "suspend";
    setBusy(busyKey(workspace.id, action));
    startTransition(async () => {
      const res = await updateWorkspaceStatus(workspace.id, status, note);
      setBusy(null);
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

  const runPublish = (workspace: Workspace, published: boolean) => {
    setBusy(busyKey(workspace.id, "publish"));
    startTransition(async () => {
      const res = await setWorkspacePublished(workspace.id, published);
      setBusy(null);
      if (res.ok) {
        toast({
          tone: "ok",
          title: t(published ? "toast.published" : "toast.unpublished"),
        });
      } else {
        toast({ tone: "err", title: t("toast.publishFailed"), body: res.message });
      }
    });
  };

  // Publishing is non-destructive (one click); unpublishing hides a live
  // workspace from the public site, so it goes through the styled confirm.
  const togglePublish = async (workspace: Workspace) => {
    if (workspace.is_published) {
      const ok = await confirm({
        title: t("unpublishConfirm.title"),
        message: t("unpublishConfirm.body", { name: workspace.name }),
        confirmLabel: t("unpublish"),
        tone: "danger",
        icon: "alert",
      });
      if (!ok) return;
      runPublish(workspace, false);
    } else {
      runPublish(workspace, true);
    }
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
      id: "published",
      header: t("colPublished"),
      cell: (w) =>
        w.is_published ? (
          <Badge tone="success" dot>
            {t("publishedBadge")}
          </Badge>
        ) : (
          <Badge tone="neutral" dot>
            {t("unpublishedBadge")}
          </Badge>
        ),
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
              loading={busy === busyKey(w.id, "activate")}
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
              onClick={() => {
                setReason("");
                setSuspendTarget(w);
              }}
            >
              {t("suspend")}
            </Button>
          )}
          <Button
            variant={w.is_published ? "ghost" : "secondary"}
            size="sm"
            icon={w.is_published ? "eyeOff" : "eye"}
            loading={busy === busyKey(w.id, "publish")}
            onClick={() => togglePublish(w)}
          >
            {w.is_published ? t("unpublish") : t("publish")}
          </Button>
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
            setFilter("status", v === "all" ? null : v);
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
              setFilter("search", e.target.value);
              setPage(1);
            }}
            placeholder={t("searchPlaceholder")}
          />
        </div>
        <ExportCsvLink
          type="workspaces"
          label={t("exportCsv")}
          query={{ status: tab === "all" ? undefined : tab }}
        />
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
                loading={busy === busyKey(suspendTarget.id, "suspend")}
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
