"use client";

import { useState, useTransition } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { DataTable, Pager, type DataTableColumn } from "@/components/ui/DataTable";
import { Field } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { Tabs } from "@/components/ui/Tabs";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { approveRequest, rejectRequest } from "@/lib/actions/approvals";
import type {
  ApprovalListMeta,
  ApprovalRequestEntry,
  ApprovalStatus,
} from "@/lib/types/approvals";
import { adminDate } from "./format";

const STATUSES: readonly ApprovalStatus[] = ["pending", "approved", "rejected", "cancelled"];

const STATUS_TONE: Record<ApprovalStatus, "warning" | "success" | "danger" | "neutral"> = {
  pending: "warning",
  approved: "success",
  rejected: "danger",
  cancelled: "neutral",
};

export interface ApprovalsTableProps {
  requests: ApprovalRequestEntry[];
  meta: ApprovalListMeta;
  status: ApprovalStatus;
}

/** Render a handler-described value (number, working-hours map, null) compactly. */
function formatValue(value: unknown): string {
  if (value === null || value === undefined) return "—";
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

/** Minimal super-admin approval queue: review the change, approve or reject with a reason. */
export function ApprovalsTable({ requests, meta, status }: ApprovalsTableProps) {
  const t = useTranslations("approvals");
  const router = useRouter();
  const pathname = usePathname();
  const confirm = useConfirm();
  const { toast } = useToast();
  const [busy, startTransition] = useTransition();
  const [rejectTarget, setRejectTarget] = useState<ApprovalRequestEntry | null>(null);
  const [reason, setReason] = useState("");

  const navigate = (next: { status?: ApprovalStatus; page?: number }) => {
    const params = new URLSearchParams({
      status: next.status ?? status,
      page: String(next.page ?? 1),
    });
    router.replace(`${pathname}?${params.toString()}`);
  };

  const approve = async (entry: ApprovalRequestEntry) => {
    const ok = await confirm({
      title: t("approveTitle"),
      message: t("approveMessage"),
      confirmLabel: t("approve"),
    });
    if (!ok) return;
    startTransition(async () => {
      const res = await approveRequest(entry.id);
      if (res.ok) toast({ tone: "ok", title: t("toast.approved") });
      else toast({ tone: "err", title: t("toast.failed"), body: res.message });
    });
  };

  const submitReject = () => {
    if (!rejectTarget || reason.trim() === "") return;
    startTransition(async () => {
      const res = await rejectRequest(rejectTarget.id, reason.trim());
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.rejected") });
        setRejectTarget(null);
        setReason("");
      } else {
        toast({ tone: "err", title: t("toast.failed"), body: res.message });
      }
    });
  };

  const columns: DataTableColumn<ApprovalRequestEntry>[] = [
    {
      id: "workspace",
      header: t("colWorkspace"),
      cell: (r) => <span style={{ fontWeight: 600 }}>{r.workspace?.name ?? "—"}</span>,
    },
    { id: "type", header: t("colType"), cell: (r) => <span>{t(`types.${r.type}`)}</span> },
    {
      id: "change",
      header: t("colChange"),
      cell: (r) => (
        <span className="ltr" style={{ fontSize: 13 }}>
          {formatValue(r.change.current)} → {formatValue(r.change.proposed)}
        </span>
      ),
    },
    {
      id: "requester",
      header: t("colRequester"),
      cell: (r) => <span className="muted">{r.requester?.name ?? "—"}</span>,
    },
    {
      id: "created",
      header: t("colRequested"),
      num: true,
      cell: (r) => <span className="cell-num ltr">{adminDate(r.created_at)}</span>,
    },
    {
      id: "status",
      header: t("colStatus"),
      cell: (r) => (
        <div>
          <Badge tone={STATUS_TONE[r.status]} dot>
            {t(`status.${r.status}`)}
          </Badge>
          {r.decision_reason && (
            <div className="muted" style={{ fontSize: 12, marginTop: 4 }}>
              {r.decision_reason}
            </div>
          )}
        </div>
      ),
    },
    {
      id: "actions",
      header: "",
      cell: (r) =>
        r.status === "pending" && (
          <div className="row-actions">
            <Button variant="primary" size="sm" icon="check" disabled={busy} onClick={() => approve(r)}>
              {t("approve")}
            </Button>
            <Button variant="secondary" size="sm" icon="x" disabled={busy} onClick={() => setRejectTarget(r)}>
              {t("reject")}
            </Button>
          </div>
        ),
    },
  ];

  return (
    <div className="dt" style={{ gap: 18 }}>
      <div className="dt-toolbar">
        <Tabs
          items={STATUSES.map((id) => ({ id, label: t(`status.${id}`) }))}
          value={status}
          onChange={(v) => navigate({ status: v as ApprovalStatus })}
        />
      </div>

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={requests}
          rowKey={(r) => r.id}
          empty={<div className="empty-state">{t("empty")}</div>}
        />
        <Pager page={meta.current_page} pages={meta.last_page} setPage={(page) => navigate({ page })} />
      </div>

      {rejectTarget && (
        <Modal
          title={t("rejectTitle")}
          onClose={() => setRejectTarget(null)}
          footer={
            <>
              <Button variant="ghost" onClick={() => setRejectTarget(null)}>
                {t("cancel")}
              </Button>
              <Button variant="primary" loading={busy} disabled={reason.trim() === ""} onClick={submitReject}>
                {t("reject")}
              </Button>
            </>
          }
        >
          <Field label={t("reasonLabel")} hint={t("reasonHint")}>
            <Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={4} maxLength={1000} />
          </Field>
        </Modal>
      )}
    </div>
  );
}
