"use client";

import { useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { archivePlan, removePlan } from "@/lib/actions/financePlans";
import type { Plan } from "@/lib/types/financePlans";
import { PlanFormModal } from "./PlanFormModal";
import { money } from "./format";

interface Props {
  plans: Plan[];
}

/** Owner finance plans: list with create, edit, archive and delete. */
export function PlanManager({ plans }: Props) {
  const t = useTranslations("finance.plans");
  const locale = useLocale();
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [editor, setEditor] = useState<{ plan: Plan | null } | null>(null);

  const name = (plan: Plan) => (locale === "ar" ? plan.name_ar : plan.name_en);

  const onRemove = async (plan: Plan) => {
    const ok = await confirm({
      title: t("removeTitle"),
      message: t("removeConfirm", { name: name(plan) }),
      confirmLabel: t("remove"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;

    startTransition(async () => {
      const res = await removePlan(plan.id);
      const title = res.ok && !res.data?.deleted ? t("archived") : t("removed");
      toast(res.ok ? { tone: "ok", title } : { tone: "err", title: t("error"), body: res.message });
    });
  };

  const onArchive = (plan: Plan) =>
    startTransition(async () => {
      const res = await archivePlan(plan.id);
      toast(
        res.ok
          ? { tone: "ok", title: t("archived") }
          : { tone: "err", title: t("error"), body: res.message },
      );
    });

  const columns: DataTableColumn<Plan>[] = [
    { id: "code", header: t("fields.code"), cell: (p) => <span className="ltr">{p.code}</span> },
    { id: "name", header: t("fields.name"), cell: (p) => name(p) },
    { id: "method", header: t("fields.method"), cell: (p) => t(`methods.${p.pricing_method}`) },
    {
      id: "price",
      header: t("fields.price"),
      num: true,
      cell: (p) => (
        <span>
          {money(p.effective_price)}
          {p.effective_price !== p.price && <Badge tone="success">{t("promo")}</Badge>}
        </span>
      ),
    },
    {
      id: "seat",
      header: t("fields.dedicatedSeat"),
      cell: (p) => (p.is_dedicated_seat ? <Badge tone="neutral">{t("dedicated")}</Badge> : "—"),
    },
    {
      id: "actions",
      header: "",
      cell: (p) => (
        <div className="row" style={{ gap: 6 }}>
          <Button variant="secondary" size="sm" icon="edit" aria-label={t("edit")} onClick={() => setEditor({ plan: p })} />
          <Button variant="ghost" size="sm" icon="layers" aria-label={t("archive")} loading={pending} onClick={() => onArchive(p)} />
          <Button variant="ghost" size="sm" icon="trash" aria-label={t("remove")} loading={pending} onClick={() => onRemove(p)} />
        </div>
      ),
    },
  ];

  return (
    <section className="card card-pad stack" style={{ gap: 16 }}>
      <div className="between row wrap" style={{ gap: 10 }}>
        <span />
        <Button variant="primary" icon="plus" onClick={() => setEditor({ plan: null })}>
          {t("add")}
        </Button>
      </div>

      <DataTable
        columns={columns}
        rows={plans}
        rowKey={(p) => p.id}
        empty={<p className="muted" style={{ margin: 0 }}>{t("empty")}</p>}
      />

      {editor && <PlanFormModal plan={editor.plan} onClose={() => setEditor(null)} />}
    </section>
  );
}
