"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import {
  DataTable,
  FilterChips,
  Pager,
  sortRows,
  type DataTableColumn,
  type FilterChip,
  type SortState,
} from "@/components/ui/DataTable";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { StatTile } from "@/components/ui/StatTile";
import { Textarea } from "@/components/ui/Textarea";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { useToast } from "@/components/providers/ToastProvider";
import { useUrlFilters } from "@/lib/hooks/useUrlFilters";
import {
  createResource,
  deleteResource,
  updateResource,
  type ResourceInput,
} from "@/lib/actions/owner";
import {
  RESOURCE_STATUSES,
  RESOURCE_TYPES,
  type Resource,
  type ResourceStatus,
  type ResourceSummary,
  type ResourceType,
} from "@/lib/types/management";

const STATUS_CLASS: Record<ResourceStatus, string> = {
  available: "badge-success",
  in_use: "badge-info",
  maintenance: "badge-warning",
  unavailable: "badge-neutral",
};

const STATUS_ICON: Record<ResourceStatus, "checkCircle" | "users" | "settings" | "xCircle"> = {
  available: "checkCircle",
  in_use: "users",
  maintenance: "settings",
  unavailable: "xCircle",
};

const FILTER_KEYS = ["type", "status"] as const;
const PAGE_SIZE = 8;

export interface ResourcesTableProps {
  resources: Resource[];
  summary: ResourceSummary;
}

/** Owner workspace resources: status counts, type/status filters, CRUD. */
export function ResourcesTable({ resources, summary }: ResourcesTableProps) {
  const t = useTranslations("management.resources");
  const tc = useTranslations();
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [busyId, setBusyId] = useState<string | null>(null);
  const [editing, setEditing] = useState<
    { mode: "create" } | { mode: "edit"; resource: Resource } | null
  >(null);

  const { filters, setFilter, clearFilters } = useUrlFilters(FILTER_KEYS);
  const type = (filters.type as ResourceType | "") || "all";
  const status = (filters.status as ResourceStatus | "") || "all";

  const [sort, setSort] = useState<SortState | null>({ key: "name", dir: "asc" });
  const [page, setPage] = useState(1);

  const typeLabel = (v: ResourceType) => t(`type.${v}`);
  const statusLabel = (v: ResourceStatus) => t(`status.${v}`);

  const filtered = useMemo(() => {
    let rows = resources;
    if (type !== "all") rows = rows.filter((r) => r.type === type);
    if (status !== "all") rows = rows.filter((r) => r.status === status);
    return sortRows(rows, sort, {
      name: (r) => r.name,
      type: (r) => r.type,
      quantity: (r) => r.quantity,
      status: (r) => r.status,
    });
  }, [resources, type, status, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  const chips: FilterChip[] = [];
  if (type !== "all") {
    chips.push({
      key: "type",
      label: `${t("filterType")}: ${typeLabel(type)}`,
      onRemove: () => setFilter("type", null),
    });
  }
  if (status !== "all") {
    chips.push({
      key: "status",
      label: `${t("filterStatus")}: ${statusLabel(status)}`,
      onRemove: () => setFilter("status", null),
    });
  }

  const clearAll = () => {
    clearFilters();
    setPage(1);
  };

  const askDelete = async (resource: Resource) => {
    const ok = await confirm({
      title: t("delete"),
      message: t("deleteConfirm"),
      confirmLabel: t("delete"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;
    setBusyId(resource.id);
    startTransition(async () => {
      const res = await deleteResource(resource.id);
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t("deleted") });
      } else {
        toast({ tone: "err", title: t("deleteFailed"), body: res.message });
      }
    });
  };

  const columns: DataTableColumn<Resource>[] = [
    {
      id: "name",
      header: t("colName"),
      sortable: true,
      cell: (r) => <span style={{ fontWeight: 600 }}>{r.name}</span>,
    },
    {
      id: "type",
      header: t("colType"),
      sortable: true,
      cell: (r) => <span className="badge badge-neutral">{typeLabel(r.type)}</span>,
    },
    {
      id: "quantity",
      header: t("colQuantity"),
      num: true,
      sortable: true,
      cell: (r) => <span className="cell-num tnum">{r.quantity}</span>,
    },
    {
      id: "status",
      header: t("colStatus"),
      sortable: true,
      cell: (r) => (
        <span className={`badge ${STATUS_CLASS[r.status]}`}>
          <span className="dot" />
          {statusLabel(r.status)}
        </span>
      ),
    },
    {
      id: "notes",
      header: t("colNotes"),
      cell: (r) => (
        <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
          {r.notes || "—"}
        </span>
      ),
    },
    {
      id: "actions",
      header: "",
      cell: (r) => (
        <div className="row-actions" style={{ gap: 6 }}>
          <Button
            variant="secondary"
            size="sm"
            icon="edit"
            onClick={() => setEditing({ mode: "edit", resource: r })}
            aria-label={t("edit")}
          />
          <Button
            variant="ghost"
            size="sm"
            icon="trash"
            loading={pending && busyId === r.id}
            onClick={() => void askDelete(r)}
            aria-label={t("delete")}
          />
        </div>
      ),
    },
  ];

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="grid-stats grid-stats--5">
        <StatTile icon="layers" label={t("summaryTotal")} value={summary.total} />
        {RESOURCE_STATUSES.map((s) => (
          <StatTile
            key={s}
            icon={STATUS_ICON[s]}
            label={statusLabel(s)}
            value={summary.by_status[s] ?? 0}
          />
        ))}
      </div>

      <div className="dt">
        <div className="dt-toolbar">
          <div className="dt-tools">
            <Select
              value={type}
              onChange={(e) => {
                setFilter("type", e.target.value === "all" ? null : e.target.value);
                setPage(1);
              }}
              aria-label={t("filterType")}
            >
              <option value="all">{tc("common.all")}</option>
              {RESOURCE_TYPES.map((v) => (
                <option key={v} value={v}>
                  {typeLabel(v)}
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
              <option value="all">{tc("common.all")}</option>
              {RESOURCE_STATUSES.map((v) => (
                <option key={v} value={v}>
                  {statusLabel(v)}
                </option>
              ))}
            </Select>
          </div>
          <div className="spacer" />
          <Button
            variant="primary"
            icon="plus"
            onClick={() => setEditing({ mode: "create" })}
          >
            {t("add")}
          </Button>
        </div>

        {chips.length > 0 && (
          <FilterChips
            chips={chips}
            onClear={clearAll}
            filtersLabel={t("filters")}
            clearLabel={t("clearAll")}
          />
        )}

        <div className="table-wrap">
          <DataTable
            columns={columns}
            rows={pageRows}
            rowKey={(r) => r.id}
            sort={sort}
            setSort={setSort}
            empty={
              <div className="empty-state">
                <div className="st-ico" style={{ width: 48, height: 48 }}>
                  <Icon name="layers" />
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
      </div>

      {editing && (
        <ResourceForm
          resource={editing.mode === "edit" ? editing.resource : null}
          onClose={() => setEditing(null)}
        />
      )}
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Create / edit modal                                                       */
/* -------------------------------------------------------------------------- */

interface ResourceFormProps {
  resource: Resource | null;
  onClose: () => void;
}

interface ResourceFormState {
  name: string;
  type: ResourceType;
  quantity: string;
  status: ResourceStatus;
  notes: string;
}

function ResourceForm({ resource, onClose }: ResourceFormProps) {
  const t = useTranslations("management.resources");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [form, setForm] = useState<ResourceFormState>(() => ({
    name: resource?.name ?? "",
    type: resource?.type ?? "meeting_room",
    quantity: resource ? String(resource.quantity) : "1",
    status: resource?.status ?? "available",
    notes: resource?.notes ?? "",
  }));
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const isEdit = resource !== null;
  const set = <K extends keyof ResourceFormState>(key: K, value: ResourceFormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  const submit = () => {
    const payload: ResourceInput = {
      name: form.name.trim(),
      type: form.type,
      quantity: Number(form.quantity) || 0,
      status: form.status,
      notes: form.notes.trim() || null,
    };

    startTransition(async () => {
      const res = isEdit
        ? await updateResource(resource.id, payload)
        : await createResource(payload);

      if (res.ok) {
        toast({ tone: "ok", title: isEdit ? t("updated") : t("created") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("saveFailed"), body: res.message });
      }
    });
  };

  return (
    <Modal
      title={isEdit ? t("editTitle") : t("newTitle")}
      icon="layers"
      onClose={onClose}
      closeLabel={t("cancel")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t("cancel")}
          </Button>
          <Button variant="primary" icon="check" loading={pending} onClick={submit}>
            {t("save")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 16 }}>
        <Field label={t("fName")} error={errors.name?.[0]}>
          <Input value={form.name} onChange={(e) => set("name", e.target.value)} />
        </Field>

        <div className="grid2">
          <Field label={t("fType")} error={errors.type?.[0]}>
            <Select
              value={form.type}
              onChange={(e) => set("type", e.target.value as ResourceType)}
            >
              {RESOURCE_TYPES.map((v) => (
                <option key={v} value={v}>
                  {t(`type.${v}`)}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("fQuantity")} error={errors.quantity?.[0]}>
            <Input
              className="tnum"
              inputMode="numeric"
              value={form.quantity}
              onChange={(e) => set("quantity", e.target.value)}
            />
          </Field>
        </div>

        <Field label={t("fStatus")} error={errors.status?.[0]}>
          <Select
            value={form.status}
            onChange={(e) => set("status", e.target.value as ResourceStatus)}
          >
            {RESOURCE_STATUSES.map((v) => (
              <option key={v} value={v}>
                {t(`status.${v}`)}
              </option>
            ))}
          </Select>
        </Field>

        <Field label={t("fNotes")} error={errors.notes?.[0]}>
          <Textarea
            rows={3}
            value={form.notes}
            onChange={(e) => set("notes", e.target.value)}
          />
        </Field>
      </div>
    </Modal>
  );
}
