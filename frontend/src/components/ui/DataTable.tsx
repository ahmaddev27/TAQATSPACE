"use client";

import {
  useEffect,
  useRef,
  type InputHTMLAttributes,
  type ReactNode,
} from "react";
import { Icon } from "./Icon";

/* -------------------------------------------------------------------------- */
/*  Sorting                                                                    */
/* -------------------------------------------------------------------------- */

export interface SortState {
  key: string;
  dir: "asc" | "desc";
}

/** Per-column value accessors for generic sorting. */
export type SortAccessors<T> = Record<string, (row: T) => string | number>;

/** Stable, locale-aware sort of `rows` by the active `sort` state. */
export function sortRows<T>(
  rows: T[],
  sort: SortState | null,
  accessors: SortAccessors<T> = {},
): T[] {
  if (!sort) return rows;
  const get =
    accessors[sort.key] ??
    ((row: T) => (row as Record<string, unknown>)[sort.key] as string | number);

  const out = [...rows].sort((a, b) => {
    const va = get(a);
    const vb = get(b);
    if (typeof va === "number" && typeof vb === "number") return va - vb;
    return String(va).localeCompare(String(vb), "ar");
  });

  return sort.dir === "desc" ? out.reverse() : out;
}

/* -------------------------------------------------------------------------- */
/*  Checkbox (indeterminate-capable)                                           */
/* -------------------------------------------------------------------------- */

export interface CbxProps
  extends Omit<InputHTMLAttributes<HTMLInputElement>, "checked" | "onChange"> {
  checked?: boolean;
  indeterminate?: boolean;
  onChange?: InputHTMLAttributes<HTMLInputElement>["onChange"];
}

export function Cbx({ checked, indeterminate, onChange, ...rest }: CbxProps) {
  const ref = useRef<HTMLInputElement>(null);
  useEffect(() => {
    if (ref.current) ref.current.indeterminate = !!indeterminate && !checked;
  }, [indeterminate, checked]);

  return (
    <input
      ref={ref}
      type="checkbox"
      className="cbx"
      checked={!!checked}
      onChange={onChange}
      onClick={(e) => e.stopPropagation()}
      {...rest}
    />
  );
}

/* -------------------------------------------------------------------------- */
/*  Sortable header cell                                                       */
/* -------------------------------------------------------------------------- */

export interface ThProps {
  id: string;
  sort?: SortState | null;
  setSort?: (sort: SortState) => void;
  children?: ReactNode;
  num?: boolean;
  /** Non-sortable header. */
  plain?: boolean;
}

export function Th({ id, sort, setSort, children, num, plain }: ThProps) {
  if (plain || !setSort) {
    return <th className={num ? "num" : undefined}>{children}</th>;
  }

  const active = sort?.key === id;
  const cls = [
    "sortable",
    active ? "active" : "",
    active && sort?.dir === "desc" ? "desc" : "",
    num ? "num" : "",
  ]
    .filter(Boolean)
    .join(" ");

  const toggle = () =>
    setSort(
      active
        ? { key: id, dir: sort?.dir === "asc" ? "desc" : "asc" }
        : { key: id, dir: "asc" },
    );

  return (
    <th className={cls} onClick={toggle}>
      {children}
      <span className="sort-i">
        <Icon name="arrowUp" size={13} />
      </span>
    </th>
  );
}

/* -------------------------------------------------------------------------- */
/*  Pager                                                                      */
/* -------------------------------------------------------------------------- */

export interface PagerProps {
  page: number;
  pages: number;
  setPage: (page: number) => void;
}

export function Pager({ page, pages, setPage }: PagerProps) {
  if (pages <= 1) {
    return (
      <div className="pager">
        <button type="button" className="pg-num" aria-current="true">
          1
        </button>
      </div>
    );
  }

  const nums = Array.from({ length: pages }, (_, i) => i + 1);
  return (
    <div className="pager">
      <button
        type="button"
        className="pg-edge"
        disabled={page === 1}
        onClick={() => setPage(page - 1)}
        aria-label="prev"
      >
        <Icon name="chevL" size={16} />
      </button>
      {nums.map((n) => (
        <button
          key={n}
          type="button"
          className="pg-num"
          aria-current={page === n}
          onClick={() => setPage(n)}
        >
          {n}
        </button>
      ))}
      <button
        type="button"
        className="pg-edge"
        disabled={page === pages}
        onClick={() => setPage(page + 1)}
        aria-label="next"
      >
        <Icon name="chevR" size={16} />
      </button>
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Filter chips                                                               */
/* -------------------------------------------------------------------------- */

export interface FilterChip {
  key: string;
  label: ReactNode;
  onRemove: () => void;
}

export interface FilterChipsProps {
  chips: FilterChip[];
  onClear: () => void;
  /** Localized leading label, e.g. "Filters:". */
  filtersLabel?: ReactNode;
  /** Localized clear-all label. */
  clearLabel?: ReactNode;
}

export function FilterChips({
  chips,
  onClear,
  filtersLabel = "Filters:",
  clearLabel = "Clear all",
}: FilterChipsProps) {
  if (chips.length === 0) return null;
  return (
    <div className="filter-chips">
      <span className="fc-label">{filtersLabel}</span>
      {chips.map((c) => (
        <span key={c.key} className="chip">
          {c.label}
          <button type="button" onClick={c.onRemove} aria-label="remove">
            <Icon name="x" size={13} />
          </button>
        </span>
      ))}
      <button type="button" className="chip-clear" onClick={onClear}>
        {clearLabel}
      </button>
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  DataTable                                                                  */
/* -------------------------------------------------------------------------- */

export interface DataTableColumn<T> {
  id: string;
  header: ReactNode;
  /** Cell renderer. */
  cell: (row: T) => ReactNode;
  num?: boolean;
  /** Sortable when an accessor is provided via `sortAccessors`. */
  sortable?: boolean;
}

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[];
  rows: T[];
  rowKey: (row: T) => string;
  sort?: SortState | null;
  setSort?: (sort: SortState) => void;
  onRowClick?: (row: T) => void;
  /** Rendered (full-width) when there are no rows. */
  empty?: ReactNode;
  /** Leading checkbox column. */
  selectable?: boolean;
  isSelected?: (row: T) => boolean;
  onToggleRow?: (row: T) => void;
  allSelected?: boolean;
  someSelected?: boolean;
  onToggleAll?: () => void;
}

/**
 * Generic, presentational data table built on the prototype's `tbl` system.
 * Sorting/pagination/selection state is owned by the caller; this only renders.
 */
export function DataTable<T>({
  columns,
  rows,
  rowKey,
  sort,
  setSort,
  onRowClick,
  empty,
  selectable,
  isSelected,
  onToggleRow,
  allSelected,
  someSelected,
  onToggleAll,
}: DataTableProps<T>) {
  const colSpan = columns.length + (selectable ? 1 : 0);

  return (
    <div className="tbl-scroll">
      <table className="tbl">
        <thead>
          <tr>
            {selectable && (
              <th className="col-check">
                <Cbx
                  checked={allSelected}
                  indeterminate={someSelected}
                  onChange={onToggleAll}
                />
              </th>
            )}
            {columns.map((col) =>
              col.sortable ? (
                <Th
                  key={col.id}
                  id={col.id}
                  sort={sort}
                  setSort={setSort}
                  num={col.num}
                >
                  {col.header}
                </Th>
              ) : (
                <Th key={col.id} id={col.id} num={col.num} plain>
                  {col.header}
                </Th>
              ),
            )}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => {
            const selected = selectable && isSelected?.(row);
            return (
              <tr
                key={rowKey(row)}
                className={selected ? "is-selected" : undefined}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                style={onRowClick ? { cursor: "pointer" } : undefined}
              >
                {selectable && (
                  <td className="col-check">
                    <Cbx
                      checked={isSelected?.(row)}
                      onChange={() => onToggleRow?.(row)}
                    />
                  </td>
                )}
                {columns.map((col) => (
                  <td key={col.id} className={col.num ? "cell-num" : undefined}>
                    {col.cell(row)}
                  </td>
                ))}
              </tr>
            );
          })}
          {rows.length === 0 && empty && (
            <tr>
              <td colSpan={colSpan}>{empty}</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
