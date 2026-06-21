"use client";

import { useMemo, useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import {
  DataTable,
  Pager,
  sortRows,
  type DataTableColumn,
  type SortState,
} from "@/components/ui/DataTable";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { useToast } from "@/components/providers/ToastProvider";
import {
  createCity,
  deleteCity,
  updateCity,
  type CityInput,
} from "@/lib/actions/admin";
import type { City } from "@/lib/types";

const PAGE_SIZE = 10;

type Editing = { mode: "create" } | { mode: "edit"; city: City } | null;

export interface CitiesTableProps {
  cities: City[];
}

/** Admin city directory: localized name, active badge, usage count, CRUD. */
export function CitiesTable({ cities }: CitiesTableProps) {
  const t = useTranslations("admin.cities");
  const locale = useLocale();
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [busyId, setBusyId] = useState<string | null>(null);

  const [search, setSearch] = useState("");
  const [sort, setSort] = useState<SortState | null>({ key: "name", dir: "asc" });
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<Editing>(null);

  const cityName = (c: City) => (locale === "ar" ? c.name_ar : c.name_en);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    let rows = cities;
    if (q) {
      rows = rows.filter(
        (c) =>
          c.name_ar.toLowerCase().includes(q) ||
          c.name_en.toLowerCase().includes(q),
      );
    }
    return sortRows(rows, sort, {
      name: (c) => cityName(c),
      name_en: (c) => c.name_en,
      workspaces: (c) => c.workspaces_count ?? 0,
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cities, search, sort, locale]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const handleDelete = async (city: City) => {
    const ok = await confirm({
      title: t("deleteTitle"),
      message: t("deleteConfirm", { name: cityName(city) }),
      confirmLabel: t("delete"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;
    setBusyId(city.id);
    startTransition(async () => {
      const res = await deleteCity(city.id);
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.deleted"), body: res.message });
      } else {
        toast({ tone: "err", title: t("toast.deleteFailed"), body: res.message });
      }
    });
  };

  const columns: DataTableColumn<City>[] = [
    {
      id: "name",
      header: t("colNameAr"),
      sortable: true,
      cell: (c) => <span style={{ fontWeight: 600 }}>{c.name_ar}</span>,
    },
    {
      id: "name_en",
      header: t("colNameEn"),
      sortable: true,
      cell: (c) => <span className="ltr">{c.name_en}</span>,
    },
    {
      id: "status",
      header: t("colStatus"),
      cell: (c) => (
        <Badge tone={c.is_active ? "success" : "neutral"} dot>
          {c.is_active ? t("active") : t("inactive")}
        </Badge>
      ),
    },
    {
      id: "workspaces",
      header: t("colWorkspaces"),
      num: true,
      sortable: true,
      cell: (c) => <span className="cell-num tnum">{c.workspaces_count ?? 0}</span>,
    },
    {
      id: "actions",
      header: "",
      cell: (c) => (
        <div className="row-actions" style={{ gap: 6 }}>
          <Button
            variant="secondary"
            size="sm"
            icon="edit"
            onClick={() => setEditing({ mode: "edit", city: c })}
            aria-label={t("edit")}
            title={t("edit")}
          />
          <Button
            variant="ghost"
            size="sm"
            icon="trash"
            loading={pending && busyId === c.id}
            onClick={() => void handleDelete(c)}
            aria-label={t("delete")}
          />
        </div>
      ),
    },
  ];

  return (
    <div className="dt" style={{ gap: 18 }}>
      <div className="dt-toolbar">
        <div className="spacer" />
        <div className="dt-search input-icon">
          <Icon name="search" />
          <input
            className="input"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
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
          {t("add")}
        </Button>
      </div>

      <div className="table-wrap">
        <DataTable
          columns={columns}
          rows={pageRows}
          rowKey={(c) => c.id}
          sort={sort}
          setSort={setSort}
          empty={
            <div className="empty-state">
              <div className="st-ico" style={{ width: 48, height: 48 }}>
                <Icon name="pin" />
              </div>
              <div>
                <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                  {t("emptyTitle")}
                </div>
                <div style={{ fontSize: "var(--fs-sm)" }}>{t("emptyBody")}</div>
              </div>
              <Button
                variant="primary"
                size="sm"
                icon="plus"
                onClick={() => setEditing({ mode: "create" })}
              >
                {t("add")}
              </Button>
            </div>
          }
        />
        {filtered.length > 0 && (
          <div className="table-foot">
            <span>
              {t("count")}: <strong>{filtered.length}</strong>
            </span>
            <Pager page={curPage} pages={pages} setPage={setPage} />
          </div>
        )}
      </div>

      {editing && (
        <CityFormModal
          city={editing.mode === "edit" ? editing.city : null}
          onClose={() => setEditing(null)}
        />
      )}
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Create / edit modal                                                       */
/* -------------------------------------------------------------------------- */

interface CityFormModalProps {
  city: City | null;
  onClose: () => void;
}

function CityFormModal({ city, onClose }: CityFormModalProps) {
  const t = useTranslations("admin.cities");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const isEdit = city !== null;
  const [nameAr, setNameAr] = useState(city?.name_ar ?? "");
  const [nameEn, setNameEn] = useState(city?.name_en ?? "");
  const [isActive, setIsActive] = useState(city?.is_active ?? true);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const submit = () => {
    setErrors({});
    const payload: CityInput = {
      name_ar: nameAr.trim(),
      name_en: nameEn.trim(),
      is_active: isActive,
    };

    startTransition(async () => {
      const res =
        isEdit && city
          ? await updateCity(city.id, payload)
          : await createCity(payload);

      if (res.ok) {
        toast({ tone: "ok", title: isEdit ? t("toast.updated") : t("toast.created") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("toast.saveFailed"), body: res.message });
      }
    });
  };

  return (
    <Modal
      title={isEdit ? t("editTitle") : t("createTitle")}
      icon="pin"
      onClose={onClose}
      closeLabel={t("cancel")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={pending}>
            {t("cancel")}
          </Button>
          <Button variant="primary" icon="check" loading={pending} onClick={submit}>
            {isEdit ? t("save") : t("create")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <Field label={t("form.nameAr")} error={errors.name_ar?.[0]}>
          <Input
            value={nameAr}
            onChange={(e) => setNameAr(e.target.value)}
            placeholder={t("form.nameArPlaceholder")}
          />
        </Field>
        <Field label={t("form.nameEn")} error={errors.name_en?.[0]}>
          <Input
            className="ltr"
            value={nameEn}
            onChange={(e) => setNameEn(e.target.value)}
            placeholder={t("form.nameEnPlaceholder")}
          />
        </Field>
        <Field label={t("form.status")} error={errors.is_active?.[0]}>
          <Checkbox
            checked={isActive}
            onChange={(e) => setIsActive(e.target.checked)}
          >
            {t("form.activeLabel")}
          </Checkbox>
        </Field>
      </div>
    </Modal>
  );
}
