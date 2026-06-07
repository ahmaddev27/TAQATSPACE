import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, SimpleMeta } from "@/lib/types";

/* -------------------------------------------------------------------------- */
/*  Enums (mirror App\Enums\*)                                                 */
/* -------------------------------------------------------------------------- */

export type ExpenseCategory =
  | "rent"
  | "utilities"
  | "salaries"
  | "maintenance"
  | "supplies"
  | "marketing"
  | "other";

export type ResourceType =
  | "meeting_room"
  | "private_office"
  | "equipment"
  | "parking"
  | "other";

export type ResourceStatus =
  | "available"
  | "in_use"
  | "maintenance"
  | "unavailable";

export const EXPENSE_CATEGORIES: ExpenseCategory[] = [
  "rent",
  "utilities",
  "salaries",
  "maintenance",
  "supplies",
  "marketing",
  "other",
];

export const RESOURCE_TYPES: ResourceType[] = [
  "meeting_room",
  "private_office",
  "equipment",
  "parking",
  "other",
];

export const RESOURCE_STATUSES: ResourceStatus[] = [
  "available",
  "in_use",
  "maintenance",
  "unavailable",
];

/* -------------------------------------------------------------------------- */
/*  Expenses (mirror App\Http\Resources\ExpenseResource)                       */
/* -------------------------------------------------------------------------- */

export interface Expense {
  id: string;
  title: string;
  category: ExpenseCategory;
  /** Decimal string, e.g. "120.00". */
  amount: string;
  spent_on: string | null;
  notes: string | null;
  created_at: string | null;
  updated_at: string | null;
}

/** Per-category breakdown keyed by the category value (decimal strings). */
export type ExpenseCategoryBreakdown = Partial<Record<ExpenseCategory, string>>;

export interface ExpenseSummary {
  total: string;
  this_month: string;
  by_category: ExpenseCategoryBreakdown;
}

export interface ExpensesResult {
  expenses: Expense[];
  summary: ExpenseSummary;
  meta: SimpleMeta;
}

export interface ExpensesParams {
  category?: ExpenseCategory | "all";
  from?: string;
  to?: string;
  per_page?: number;
  page?: number;
}

/* -------------------------------------------------------------------------- */
/*  Resources (mirror App\Http\Resources\ResourceResource)                     */
/* -------------------------------------------------------------------------- */

export interface Resource {
  id: string;
  name: string;
  type: ResourceType;
  quantity: number;
  status: ResourceStatus;
  notes: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export type ResourceStatusCounts = Partial<Record<ResourceStatus, number>>;

export interface ResourceSummary {
  total: number;
  by_status: ResourceStatusCounts;
}

export interface ResourcesResult {
  resources: Resource[];
  summary: ResourceSummary;
  meta: SimpleMeta;
}

export interface ResourcesParams {
  type?: ResourceType | "all";
  status?: ResourceStatus | "all";
  per_page?: number;
  page?: number;
}

/* -------------------------------------------------------------------------- */
/*  Queries                                                                     */
/* -------------------------------------------------------------------------- */

function toQuery(params: ExpensesParams | ResourcesParams): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/** Owner: operating expenses for THIS workspace only (list + summary). */
export async function ownerExpenses(
  params: ExpensesParams = {},
): Promise<ExpensesResult> {
  const res = await serverFetch<ApiEnvelope<ExpensesResult>>(
    `/workspace/expenses${toQuery(params)}`,
  );
  return res.data;
}

/** Owner: resources for THIS workspace only (list + counts summary). */
export async function ownerResources(
  params: ResourcesParams = {},
): Promise<ResourcesResult> {
  const res = await serverFetch<ApiEnvelope<ResourcesResult>>(
    `/workspace/resources${toQuery(params)}`,
  );
  return res.data;
}
