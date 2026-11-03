"use client";

import { useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Field } from "@/components/ui/Field";
import { FileDropzone } from "@/components/ui/FileDropzone";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { StatTile } from "@/components/ui/StatTile";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import {
  activateHourPurchase,
  adjustHourBalance,
  cancelHourPurchase,
} from "@/lib/actions/hourPackages";
import type {
  HourPurchase,
  HourPurchaseStats,
  HourPurchaseStatus,
} from "@/lib/api/hourPackages";
import { adminDate } from "./format";

const RECEIPT_ACCEPT = "application/pdf,image/png,image/jpeg,image/jpg";
const TONES: Record<HourPurchaseStatus, "warning" | "success" | "neutral" | "danger"> = {
  requested: "warning",
  active: "success",
  exhausted: "neutral",
  cancelled: "danger",
  expired: "neutral",
};

export interface HourPurchasesTableProps {
  purchases: HourPurchase[];
  stats: HourPurchaseStats;
}

/** Super-admin purchase queue: activate after an offline payment, cancel, adjust balance. */
export function HourPurchasesTable({ purchases, stats }: HourPurchasesTableProps) {
  const t = useTranslations("admin.hourPurchases");
  const locale = useLocale();
  const confirm = useConfirm();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [target, setTarget] = useState<HourPurchase | null>(null);
  const [method, setMethod] = useState<"cash" | "transfer">("transfer");
  const [paidAt, setPaidAt] = useState("");
  const [receipt, setReceipt] = useState<File | null>(null);

  const [adjusting, setAdjusting] = useState<HourPurchase | null>(null);
  const [minutes, setMinutes] = useState("");
  const [reason, setReason] = useState("");

  const report = (ok: boolean, okTitle: string, message?: string) =>
    toast(ok ? { tone: "ok", title: okTitle } : { tone: "err", title: t("toast.failed"), body: message });

  const closeActivate = () => {
    setTarget(null);
    setPaidAt("");
    setReceipt(null);
  };

  const activate = () => {
    if (!target || !receipt) return;
    startTransition(async () => {
      const res = await activateHourPurchase(target.id, {
        payment_method: method,
        paid_at: paidAt,
        receipt,
      });
      report(res.ok, t("toast.activated"), res.ok ? undefined : res.message);
      if (res.ok) closeActivate();
    });
  };

  const cancel = async (purchase: HourPurchase) => {
    const ok = await confirm({
      title: t("cancelDialog.title"),
      message: t("cancelDialog.message", { name: purchase.user?.name ?? "" }),
      confirmLabel: t("cancelDialog.confirm"),
      tone: "danger",
    });
    if (!ok) return;
    const res = await cancelHourPurchase(purchase.id);
    report(res.ok, t("toast.cancelled"), res.ok ? undefined : res.message);
  };

  const adjust = () => {
    if (!adjusting?.user) return;
    const user_id = adjusting.user.id;
    startTransition(async () => {
      const res = await adjustHourBalance({
        user_id,
        minutes: Math.round(Number(minutes) * 60),
        reason,
      });
      report(res.ok, t("toast.adjusted"), res.ok ? undefined : res.message);
      if (res.ok) setAdjusting(null);
    });
  };

  const columns: DataTableColumn<HourPurchase>[] = [
    {
      id: "user",
      header: t("colUser"),
      cell: (p) => (
        <div>
          <div style={{ fontWeight: 600 }}>{p.user?.name ?? "—"}</div>
          <div className="muted ltr">{p.user?.email}</div>
        </div>
      ),
    },
    {
      id: "package",
      header: t("colPackage"),
      cell: (p) => (locale === "ar" ? p.package?.name_ar : p.package?.name_en) ?? "—",
    },
    { id: "hours", header: t("colHours"), num: true, cell: (p) => <span className="cell-num">{p.hours}</span> },
    {
      id: "price",
      header: t("colPrice"),
      num: true,
      cell: (p) => <span className="cell-num ltr">{p.price} {p.currency}</span>,
    },
    {
      id: "requested",
      header: t("colRequested"),
      num: true,
      cell: (p) => <span className="cell-num ltr">{adminDate(p.created_at)}</span>,
    },
    {
      id: "status",
      header: t("colStatus"),
      cell: (p) => <Badge tone={TONES[p.status]}>{t(`status.${p.status}`)}</Badge>,
    },
    {
      id: "actions",
      header: "",
      cell: (p) => (
        <div className="row-actions">
          {p.status === "requested" && (
            <>
              <Button variant="primary" size="sm" icon="check" onClick={() => setTarget(p)}>
                {t("activate")}
              </Button>
              <Button variant="ghost" size="sm" icon="x" onClick={() => cancel(p)}>
                {t("cancel")}
              </Button>
            </>
          )}
          <Button variant="ghost" size="sm" icon="clock" onClick={() => setAdjusting(p)}>
            {t("adjust")}
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="dt" style={{ gap: 18 }}>
      <div className="grid-stats">
        <StatTile icon="clock" label={t("status.requested")} value={stats.requested} />
        <StatTile icon="checkCircle" label={t("status.active")} value={stats.active} />
        <StatTile icon="xCircle" label={t("status.cancelled")} value={stats.cancelled} />
      </div>

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={purchases}
          rowKey={(p) => p.id}
          sort={null}
          setSort={() => undefined}
          empty={<div className="empty-state">{t("empty")}</div>}
        />
      </div>

      {target && (
        <Modal
          title={t("activateDialog.title")}
          icon="check"
          onClose={closeActivate}
          footer={
            <>
              <Button variant="ghost" onClick={closeActivate}>
                {t("close")}
              </Button>
              <Button variant="primary" icon="check" loading={pending} disabled={!paidAt || !receipt} onClick={activate}>
                {t("activateDialog.confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted">{t("activateDialog.body", { hours: target.hours })}</p>
            <Field label={t("activateDialog.method")}>
              <Select value={method} onChange={(e) => setMethod(e.target.value as "cash" | "transfer")}>
                <option value="transfer">{t("activateDialog.transfer")}</option>
                <option value="cash">{t("activateDialog.cash")}</option>
              </Select>
            </Field>
            <Field label={t("activateDialog.paidAt")}>
              <Input className="ltr" type="date" value={paidAt} onChange={(e) => setPaidAt(e.target.value)} />
            </Field>
            <Field label={t("activateDialog.receipt")}>
              <FileDropzone
                value={receipt}
                onChange={setReceipt}
                accept={RECEIPT_ACCEPT}
                label={t("activateDialog.dropLabel")}
                hint={t("activateDialog.dropHint")}
                clearLabel={t("activateDialog.remove")}
              />
            </Field>
          </div>
        </Modal>
      )}

      {adjusting && (
        <Modal
          title={t("adjustDialog.title")}
          icon="clock"
          onClose={() => setAdjusting(null)}
          footer={
            <>
              <Button variant="ghost" onClick={() => setAdjusting(null)}>
                {t("close")}
              </Button>
              <Button variant="primary" icon="check" loading={pending} disabled={!minutes || reason.trim().length < 3} onClick={adjust}>
                {t("adjustDialog.confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted">{t("adjustDialog.body", { name: adjusting.user?.name ?? "" })}</p>
            <Field label={t("adjustDialog.hours")} hint={t("adjustDialog.hoursHint")}>
              <Input className="ltr" type="number" step="0.25" value={minutes} onChange={(e) => setMinutes(e.target.value)} />
            </Field>
            <Field label={t("adjustDialog.reason")}>
              <Textarea value={reason} onChange={(e) => setReason(e.target.value)} />
            </Field>
          </div>
        </Modal>
      )}
    </div>
  );
}
