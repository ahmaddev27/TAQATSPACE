"use client";

import { useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { useToast } from "@/components/providers/ToastProvider";
import {
  archiveHourPackage,
  createHourPackage,
  updateHourPackage,
  type HourPackageInput,
} from "@/lib/actions/hourPackages";
import type { HourPackage } from "@/lib/api/hourPackages";

const EMPTY: HourPackageInput = {
  code: "",
  name_ar: "",
  name_en: "",
  hours: 8,
  price: 10,
  currency: "USD",
  validity_days: null,
  is_active: true,
};

export interface HourPackagesTableProps {
  packages: HourPackage[];
}

/** Super-admin catalog of platform hour packages: create, edit, archive. */
export function HourPackagesTable({ packages }: HourPackagesTableProps) {
  const t = useTranslations("admin.hourPackages");
  const locale = useLocale();
  const confirm = useConfirm();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [editing, setEditing] = useState<HourPackage | "new" | null>(null);
  const [form, setForm] = useState<HourPackageInput>(EMPTY);

  const open = (target: HourPackage | "new") => {
    setForm(target === "new" ? EMPTY : { ...target, price: Number(target.price) });
    setEditing(target);
  };

  const save = () => {
    if (!editing) return;
    startTransition(async () => {
      const res =
        editing === "new"
          ? await createHourPackage(form)
          : await updateHourPackage(editing.id, form);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.saved") });
        setEditing(null);
      } else {
        toast({ tone: "err", title: t("toast.failed"), body: res.message });
      }
    });
  };

  const archive = async (pkg: HourPackage) => {
    const ok = await confirm({
      title: t("archive.title"),
      message: t("archive.message", { name: locale === "ar" ? pkg.name_ar : pkg.name_en }),
      confirmLabel: t("archive.confirm"),
      tone: "danger",
    });
    if (!ok) return;
    const res = await archiveHourPackage(pkg.id);
    toast(
      res.ok
        ? { tone: "ok", title: t("toast.archived") }
        : { tone: "err", title: t("toast.failed"), body: res.message },
    );
  };

  const set = <K extends keyof HourPackageInput>(key: K, value: HourPackageInput[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  const columns: DataTableColumn<HourPackage>[] = [
    { id: "code", header: t("colCode"), cell: (p) => <span className="ltr">{p.code}</span> },
    {
      id: "name",
      header: t("colName"),
      cell: (p) => <span>{locale === "ar" ? p.name_ar : p.name_en}</span>,
    },
    { id: "hours", header: t("colHours"), num: true, cell: (p) => <span className="cell-num">{p.hours}</span> },
    {
      id: "price",
      header: t("colPrice"),
      num: true,
      cell: (p) => <span className="cell-num ltr">{p.price} {p.currency}</span>,
    },
    {
      id: "validity",
      header: t("colValidity"),
      cell: (p) => (p.validity_days ? t("days", { count: p.validity_days }) : t("noExpiry")),
    },
    {
      id: "status",
      header: t("colStatus"),
      cell: (p) => (
        <Badge tone={p.is_active ? "success" : "neutral"}>
          {p.is_active ? t("active") : t("archived")}
        </Badge>
      ),
    },
    {
      id: "actions",
      header: "",
      cell: (p) => (
        <div className="row-actions">
          <Button variant="ghost" size="sm" icon="edit" onClick={() => open(p)}>
            {t("edit")}
          </Button>
          {p.is_active && (
            <Button variant="ghost" size="sm" icon="trash" onClick={() => archive(p)}>
              {t("archive.action")}
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="dt" style={{ gap: 18 }}>
      <div className="dt-toolbar">
        <div className="spacer" />
        <Button variant="primary" icon="plus" onClick={() => open("new")}>
          {t("create")}
        </Button>
      </div>
      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={packages}
          rowKey={(p) => p.id}
          sort={null}
          setSort={() => undefined}
          empty={<div className="empty-state">{t("empty")}</div>}
        />
      </div>

      {editing && (
        <Modal
          title={editing === "new" ? t("create") : t("edit")}
          icon="layers"
          onClose={() => setEditing(null)}
          footer={
            <>
              <Button variant="ghost" onClick={() => setEditing(null)}>
                {t("cancel")}
              </Button>
              <Button variant="primary" icon="check" loading={pending} onClick={save}>
                {t("save")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 14 }}>
            <Field label={t("colCode")}>
              <Input className="ltr" value={form.code} onChange={(e) => set("code", e.target.value)} />
            </Field>
            <Field label={t("nameAr")}>
              <Input value={form.name_ar} onChange={(e) => set("name_ar", e.target.value)} />
            </Field>
            <Field label={t("nameEn")}>
              <Input className="ltr" value={form.name_en} onChange={(e) => set("name_en", e.target.value)} />
            </Field>
            <Field label={t("colHours")}>
              <Input className="ltr" type="number" min={1} value={form.hours} onChange={(e) => set("hours", Number(e.target.value))} />
            </Field>
            <Field label={t("colPrice")}>
              <Input className="ltr" type="number" min={0} step="0.01" value={form.price} onChange={(e) => set("price", Number(e.target.value))} />
            </Field>
            <Field label={t("currency")}>
              <Input className="ltr" maxLength={3} value={form.currency} onChange={(e) => set("currency", e.target.value.toUpperCase())} />
            </Field>
            <Field label={t("colValidity")} hint={t("validityHint")}>
              <Input
                className="ltr"
                type="number"
                min={1}
                value={form.validity_days ?? ""}
                onChange={(e) => set("validity_days", e.target.value ? Number(e.target.value) : null)}
              />
            </Field>
          </div>
        </Modal>
      )}
    </div>
  );
}
