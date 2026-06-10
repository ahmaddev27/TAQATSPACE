/**
 * Client-safe types + constants for the owner management module (expenses +
 * resources). Kept separate from `lib/api/management.ts` because that module
 * imports the server-only `serverFetch` (next/headers); client components must
 * import the types/enum constants from HERE so the server code never gets
 * bundled into the client. Mirrors `App\Enums\*` + the matching Resources.
 */
import type { SimpleMeta } from "@/lib/types";

/* ----------------------------------- Enums ----------------------------------- */

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

/* --------------------------------- Expenses ---------------------------------- */

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

/** A single point in the trailing monthly-spend series (`amount` is a decimal string). */
export interface ExpenseMonthlyPoint {
  /** `YYYY-MM`. */
  month: string;
  total: string;
}

export interface ExpenseSummary {
  total: string;
  this_month: string;
  by_category: ExpenseCategoryBreakdown;
  by_month: ExpenseMonthlyPoint[];
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

/* --------------------------------- Resources --------------------------------- */

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
