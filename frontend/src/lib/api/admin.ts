import { serverFetch } from "@/lib/api";
import type {
  ApiEnvelope,
  InvoiceStatus,
  Paginated,
  PlanType,
  SubscriptionStatus,
  User,
  UserRole,
  UserStatus,
  Workspace,
  WorkspaceStatus,
} from "@/lib/types";

/* -------------------------------------------------------------------------- */
/*  Dashboard stats (GET /admin/stats)                                         */
/* -------------------------------------------------------------------------- */

export interface AdminStats {
  workspaces: { active: number; pending: number; suspended: number; total: number };
  users: {
    freelancers: number;
    owners: number;
    admins: number;
    total: number;
    pending: number;
  };
  subscriptions: { active: number; total: number };
  invoices: { paid: number; pending: number; overdue: number; total: number };
  /** Admin-tracked figures, NOT gateway money. Decimal strings (e.g. "1500.00"). */
  revenue: { paid: string; outstanding: string };
}

/** Platform-wide counters for the super-admin dashboard. */
export async function getAdminStats(): Promise<AdminStats> {
  const res = await serverFetch<ApiEnvelope<AdminStats>>("/admin/stats");
  return res.data;
}

/* -------------------------------------------------------------------------- */
/*  Workspaces (GET /admin/workspaces)                                         */
/* -------------------------------------------------------------------------- */

export interface AdminWorkspaceFilters {
  status?: WorkspaceStatus;
  city?: string;
  name?: string;
  page?: number;
}

/** Admin workspace directory across all statuses, with owner info attached. */
export async function getAdminWorkspaces(
  filters: AdminWorkspaceFilters = {},
): Promise<Paginated<Workspace>> {
  const res = await serverFetch<ApiEnvelope<Paginated<Workspace>>>(
    `/admin/workspaces${toQuery(filters)}`,
  );
  return res.data;
}

/**
 * All admin workspaces flattened across pages. The admin list paginates at a
 * fixed server page size and ignores `per_page`, so the client-side table needs
 * the full set; we walk pages (capped) and concatenate. Filters narrow the set
 * server-side before the walk.
 */
export async function getAllAdminWorkspaces(
  filters: Omit<AdminWorkspaceFilters, "page"> = {},
): Promise<Workspace[]> {
  return collectPages((page) => getAdminWorkspaces({ ...filters, page }));
}

/* -------------------------------------------------------------------------- */
/*  Users (GET /admin/users)                                                   */
/* -------------------------------------------------------------------------- */

export interface AdminUserFilters {
  role?: UserRole;
  status?: UserStatus;
  search?: string;
  per_page?: number;
  page?: number;
}

/** Filtered, paginated user directory. */
export async function getAdminUsers(
  filters: AdminUserFilters = {},
): Promise<Paginated<User>> {
  const res = await serverFetch<ApiEnvelope<Paginated<User>>>(
    `/admin/users${toQuery(filters)}`,
  );
  return res.data;
}

/** All admin users flattened across pages (for the client-side table). */
export async function getAllAdminUsers(
  filters: Omit<AdminUserFilters, "page"> = {},
): Promise<User[]> {
  return collectPages((page) =>
    getAdminUsers({ per_page: 200, ...filters, page }),
  );
}

/* -------------------------------------------------------------------------- */
/*  Subscriptions (GET /admin/subscriptions)                                   */
/* -------------------------------------------------------------------------- */

/** A read-only subscription tracking row (AdminSubscriptionResource). */
export interface AdminSubscription {
  id: string;
  workspace_id: string;
  seat_id: string | null;
  plan_type: PlanType;
  /** Decimal string, e.g. "250.00". */
  monthly_price: string;
  status: SubscriptionStatus;
  start_date: string | null;
  end_date: string | null;
  cancelled_at: string | null;
  created_at: string | null;
  member: { id: string; name: string; email: string } | null;
  workspace: { id: string; name: string; city: string } | null;
}

export interface AdminSubscriptionFilters {
  status?: SubscriptionStatus;
  workspace_id?: string;
  /** Free-text search across the member name/email (backend param: `member`). */
  member?: string;
  per_page?: number;
  page?: number;
}

/** Read-only subscription tracking list. */
export async function getAdminSubscriptions(
  filters: AdminSubscriptionFilters = {},
): Promise<Paginated<AdminSubscription>> {
  const res = await serverFetch<ApiEnvelope<Paginated<AdminSubscription>>>(
    `/admin/subscriptions${toQuery(filters)}`,
  );
  return res.data;
}

/** All subscriptions flattened across pages (for the client-side table). */
export async function getAllAdminSubscriptions(
  filters: Omit<AdminSubscriptionFilters, "page"> = {},
): Promise<AdminSubscription[]> {
  return collectPages((page) =>
    getAdminSubscriptions({ per_page: 100, ...filters, page }),
  );
}

/* -------------------------------------------------------------------------- */
/*  Invoices (GET /admin/invoices)                                             */
/* -------------------------------------------------------------------------- */

/** Nested party blocks flattened onto an admin invoice row. */
export interface AdminInvoiceParty {
  id: string;
  name: string | null;
}

/** An invoice as seen by the admin (InvoiceResource with the flat chain). */
export interface AdminInvoice {
  id: string;
  invoice_number: string;
  amount: string;
  amount_formatted: string;
  currency: string;
  status: InvoiceStatus;
  due_date: string | null;
  paid_at: string | null;
  is_overdue: boolean;
  /** Absolute, viewable URLs resolved server-side (presigned S3 or public). */
  pdf_url: string | null;
  receipt_url: string | null;
  notes: string | null;
  created_at: string | null;
  member: AdminInvoiceParty | null;
  workspace: AdminInvoiceParty | null;
}

export interface AdminInvoiceFilters {
  status?: InvoiceStatus;
  search?: string;
  per_page?: number;
  page?: number;
}

/** Filtered, paginated invoice tracking list. */
export async function getAdminInvoices(
  filters: AdminInvoiceFilters = {},
): Promise<Paginated<AdminInvoice>> {
  const res = await serverFetch<ApiEnvelope<Paginated<AdminInvoice>>>(
    `/admin/invoices${toQuery(filters)}`,
  );
  return res.data;
}

/** All invoices flattened across pages (for the client-side table). */
export async function getAllAdminInvoices(
  filters: Omit<AdminInvoiceFilters, "page"> = {},
): Promise<AdminInvoice[]> {
  return collectPages((page) =>
    getAdminInvoices({ per_page: 100, ...filters, page }),
  );
}

/* -------------------------------------------------------------------------- */
/*  Helpers                                                                    */
/* -------------------------------------------------------------------------- */

/** Hard cap on page-walking to bound work on very large datasets. */
const MAX_PAGES = 50;

/**
 * Walk a paginated endpoint and concatenate every page's rows. Stops at the
 * reported `last_page` (or the safety cap), so a single call returns the whole
 * filtered set for the client-side tables.
 */
async function collectPages<T>(
  fetchPage: (page: number) => Promise<Paginated<T>>,
): Promise<T[]> {
  const first = await fetchPage(1);
  const rows = [...first.data];

  const lastPage = Math.min(first.meta.last_page, MAX_PAGES);
  for (let page = 2; page <= lastPage; page += 1) {
    const next = await fetchPage(page);
    rows.push(...next.data);
  }

  return rows;
}

/** Serialise a flat filter object to a query string, skipping empty values. */
function toQuery<T extends object>(filters: T): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value == null || value === "") continue;
    params.set(key, String(value));
  }
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}
