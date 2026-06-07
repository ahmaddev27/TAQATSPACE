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
  createExpense,
  deleteExpense,
  updateExpense,
  type ExpenseInput,
} from "@/lib/actions/owner";
import {
  EXPENSE_CATEGORIES,
  type Expense,
  type ExpenseCategory,
  type ExpenseSummary,
} from "@/lib/api/management";
import { money, shortDate } from "./format";

const CATEGORY_CLASS: Record<ExpenseCategory, string> = {
  rent: "badge-info",
  utilities: "badge-warning",
  salaries: "badge-success",
  maintenance: "badge-neutral",
  supplies: "badge-info",
  marketing: "badge-warning",
  other: "badge-neutral",
};

const FILTER_KEYS = ["category", "from", "to"] as const;
const PAGE_SIZE = 8;

export interface ExpensesTableProps {
  expenses: Expense[];
  summary: ExpenseSummary;
}

/** Owner operating expenses: summary, category + date-range filters, CRUD. */
export function ExpensesTable({ expenses, summary }: ExpensesTableProps) {
  const t = useTranslations("management.expenses");
  const tc = useTranslations();
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [busyId, setBusyId] = useState<string | null>(null);
  const [editing, setEditing] = useState<
    { mode: "create" } | { mode: "edit"; expense: Expense } | null
  >(null);

  const { filters, setFilter, clearFilters } = useUrlFilters(FILTER_KEYS);
  const category = (filters.category as ExpenseCategory | "") || "all";
  const from = filters.from;
  const to = filters.to;

  const [sort, setSort] = useState<SortState | null>({ key: "date", dir: "desc" });
  const [page, setPage] = useState(1);

  const categoryLabel = (c: ExpenseCategory) => t(`category.${c}`);

  const filtered = useMemo(() => {
    let rows = expenses;
    if (category !== "all") rows = rows.filter((e) => e.category === category);
    if (from) rows = rows.filter((e) => (e.spent_on ?? "") >= from);
    if (to) rows = rows.filter((e) => (e.spent_on ?? "") <= to);
    return sortRows(rows, sort, {
      date: (e) => e.spent_on ?? "",
      title: (e) => e.title,
      category: (e) => e.category,
      amount: (e) => Number(e.amount),
    });
  }, [expenses, category, from, to, sort]);

  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const curPage = Math.min(page, pages);
  const pageRows = filtered.slice((curPage - 1) * PAGE_SIZE, curPage * PAGE_SIZE);

  // Top spend category from the (server-computed) breakdown.
  const topCategory = useMemo(() => {
    const entries = Object.entries(summary.by_category) as Array<
      [ExpenseCategory, string]
    >;
    let best: [ExpenseCategory, number] | null = null;
    for (const [cat, value] of entries) {
      const n = Number(value);
      if (n > 0 && (best === null || n > best[1])) best = [cat, n];
    }
    return best;
  }, [summary.by_category]);

  const chips: FilterChip[] = [];
  if (category !== "all") {
    chips.push({
      key: "cat",
      label: `${t("filterCategory")}: ${categoryLabel(category)}`,
      onRemove: () => setFilter("category", null),
    });
  }
  if (from) {
    chips.push({
      key: "from",
      label: `${t("filterFrom")}: ${from}`,
      onRemove: () => setFilter("from", null),
    });
  }
  if (to) {
    chips.push({
      key: "to",
      label: `${t("filterTo")}: ${to}`,
      onRemove: () => setFilter("to", null),
    });
  }

  const clearAll = () => {
    clearFilters();
    setPage(1);
  };

  const askDelete = async (expense: Expense) => {
    const ok = await confirm({
      title: t("delete"),
      message: t("deleteConfirm"),
      confirmLabel: t("delete"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;
    setBusyId(expense.id);
    startTransition(async () => {
      const res = await deleteExpense(expense.id);
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t("deleted") });
      } else {
        toast({ tone: "err", title: t("deleteFailed"), body: res.message });
      }
    });
  };

  const columns: DataTableColumn<Expense>[] = [
    {
      id: "date",
      header: t("colDate"),
      num: true,
      sortable: true,
      cell: (e) => <span className="cell-num ltr">{shortDate(e.spent_on)}</span>,
    },
    {
      id: "title",
      header: t("colTitle"),
      sortable: true,
      cell: (e) => <span style={{ fontWeight: 600 }}>{e.title}</span>,
    },
    {
      id: "category",
      header: t("colCategory"),
      sortable: true,
      cell: (e) => (
        <span className={`badge ${CATEGORY_CLASS[e.category]}`}>
          {categoryLabel(e.category)}
        </span>
      ),
    },
    {
      id: "amount",
      header: t("colAmount"),
      num: true,
      sortable: true,
      cell: (e) => (
        <span className="cell-num" style={{ fontWeight: 600 }}>
          {money(e.amount)}
        </span>
      ),
    },
    {
      id: "notes",
      header: t("colNotes"),
      cell: (e) => (
        <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
          {e.notes || "—"}
        </span>
      ),
    },
    {
      id: "actions",
      header: "",
      cell: (e) => (
        <div className="row-actions" style={{ gap: 6 }}>
          <Button
            variant="secondary"
            size="sm"
            icon="edit"
            onClick={() => setEditing({ mode: "edit", expense: e })}
            aria-label={t("edit")}
          />
          <Button
            variant="ghost"
            size="sm"
            icon="trash"
            loading={pending && busyId === e.id}
            onClick={() => void askDelete(e)}
            aria-label={t("delete")}
          />
        </div>
      ),
    },
  ];

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="grid-stats">
        <StatTile
          icon="wallet"
          label={t("summaryTotal")}
          value={money(summary.total)}
          foot={t("summaryTotalFoot")}
        />
        <StatTile
          icon="trend"
          label={t("summaryThisMonth")}
          value={money(summary.this_month)}
          foot={t("summaryThisMonthFoot")}
        />
        <StatTile
          icon="filter"
          label={t("summaryTopCategory")}
          value={topCategory ? categoryLabel(topCategory[0]) : t("noneCategory")}
          foot={topCategory ? money(String(topCategory[1])) : t("summaryTopCategoryFoot")}
        />
      </div>

      <div className="dt">
        <div className="dt-toolbar">
          <div className="row" style={{ gap: 10, flexWrap: "wrap" }}>
            <Select
              value={category}
              onChange={(e) => {
                setFilter("category", e.target.value === "all" ? null : e.target.value);
                setPage(1);
              }}
              aria-label={t("filterCategory")}
            >
              <option value="all">{tc("common.all")}</option>
              {EXPENSE_CATEGORIES.map((c) => (
                <option key={c} value={c}>
                  {categoryLabel(c)}
                </option>
              ))}
            </Select>
            <Input
              type="date"
              value={from}
              onChange={(e) => {
                setFilter("from", e.target.value || null);
                setPage(1);
              }}
              aria-label={t("filterFrom")}
            />
            <Input
              type="date"
              value={to}
              onChange={(e) => {
                setFilter("to", e.target.value || null);
                setPage(1);
              }}
              aria-label={t("filterTo")}
            />
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
            rowKey={(e) => e.id}
            sort={sort}
            setSort={setSort}
            empty={
              <div className="empty-state">
                <div className="st-ico" style={{ width: 48, height: 48 }}>
                  <Icon name="wallet" />
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
        <ExpenseForm
          expense={editing.mode === "edit" ? editing.expense : null}
          onClose={() => setEditing(null)}
        />
      )}
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Create / edit modal                                                       */
/* -------------------------------------------------------------------------- */

interface ExpenseFormProps {
  expense: Expense | null;
  onClose: () => void;
}

interface ExpenseFormState {
  title: string;
  category: ExpenseCategory;
  amount: string;
  spentOn: string;
  notes: string;
}

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

function ExpenseForm({ expense, onClose }: ExpenseFormProps) {
  const t = useTranslations("management.expenses");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [form, setForm] = useState<ExpenseFormState>(() => ({
    title: expense?.title ?? "",
    category: expense?.category ?? "other",
    amount: expense ? String(expense.amount) : "",
    spentOn: expense?.spent_on ?? todayIso(),
    notes: expense?.notes ?? "",
  }));
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const isEdit = expense !== null;
  const set = <K extends keyof ExpenseFormState>(key: K, value: ExpenseFormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  const submit = () => {
    const payload: ExpenseInput = {
      title: form.title.trim(),
      category: form.category,
      amount: Number(form.amount) || 0,
      spent_on: form.spentOn,
      notes: form.notes.trim() || null,
    };

    startTransition(async () => {
      const res = isEdit
        ? await updateExpense(expense.id, payload)
        : await createExpense(payload);

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
      icon="wallet"
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
        <Field label={t("fTitle")} error={errors.title?.[0]}>
          <Input value={form.title} onChange={(e) => set("title", e.target.value)} />
        </Field>

        <div className="grid2">
          <Field label={t("fCategory")} error={errors.category?.[0]}>
            <Select
              value={form.category}
              onChange={(e) => set("category", e.target.value as ExpenseCategory)}
            >
              {EXPENSE_CATEGORIES.map((c) => (
                <option key={c} value={c}>
                  {t(`category.${c}`)}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("fAmount")} error={errors.amount?.[0]}>
            <Input
              className="tnum"
              inputMode="decimal"
              value={form.amount}
              onChange={(e) => set("amount", e.target.value)}
            />
          </Field>
        </div>

        <Field label={t("fDate")} error={errors.spent_on?.[0]}>
          <Input
            type="date"
            value={form.spentOn}
            onChange={(e) => set("spentOn", e.target.value)}
          />
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
