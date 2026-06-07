import { serverFetch } from "@/lib/api";
import type {
  AdminPermission,
  AdminRole,
  ApiEnvelope,
  BookingStatus,
  InvoiceStatus,
  Paginated,
  PlanType,
  PlatformMessagingConfig,
  SeatType,
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

/* --- Single-user detail (GET /admin/users/{id}) --------------------------- */

/** A subscription's derived lifecycle stage (single badge-able label). */
export type AdminUserSubscriptionLifecycle =
  | "active"
  | "upcoming"
  | "expired"
  | "cancelled"
  | "suspended";

/** One of the freelancer's subscriptions, enriched for the admin detail view. */
export interface AdminUserSubscription {
  id: string;
  plan_type: PlanType;
  /** Decimal string, e.g. "250.00". */
  monthly_price: string;
  status: SubscriptionStatus;
  start_date: string | null;
  end_date: string | null;
  cancelled_at: string | null;
  created_at: string | null;
  /** Active status AND not past its end date (server-derived). */
  is_active: boolean;
  /** Single derived lifecycle stage for badging (server-derived). */
  lifecycle: AdminUserSubscriptionLifecycle;
  /** The assigned seat's number, when a seat is linked. */
  seat_number: string | null;
  workspace: { id: string; name: string; city: string } | null;
}

/** Headline counts for a freelancer's subscriptions, bucketed by lifecycle. */
export interface AdminUserSubscriptionsSummary {
  total: number;
  active: number;
  upcoming: number;
  expired: number;
  cancelled: number;
}

/** A configured seat-type pricing tier on an owned workspace. */
export interface AdminUserSeatType {
  id: string;
  type: SeatType;
  /** Decimal string, e.g. "250.00". */
  monthly_price: string;
  /** Decimal string, e.g. "40.00". */
  daily_price: string;
  capacity: number;
  enabled: boolean;
}

/** Live seat availability for an owned workspace. */
export interface AdminUserSeatAvailability {
  total: number;
  occupied: number;
  available: number;
}

/** A workspace owned by the user, as shown on the admin detail view. */
export interface AdminUserWorkspace {
  id: string;
  name: string;
  status: WorkspaceStatus;
  city: string;
  total_seats: number;
  /** Decimal string, e.g. "250.00". */
  price_per_month: string;
  avg_rating: string | null;
  created_at: string | null;
  /** Configured seat-type pricing tiers. */
  seat_types: AdminUserSeatType[];
  /** Live seat availability (total / occupied / available). */
  seats: AdminUserSeatAvailability;
}

/** Owner verification documents resolved to viewable URLs. */
export interface AdminUserDocuments {
  license_file: string | null;
  id_document: string | null;
}

/** A recent booking request made by a freelancer. */
export interface AdminUserBooking {
  id: string;
  status: BookingStatus;
  workspace: { id: string; name: string } | null;
  created_at: string | null;
}

/**
 * Full single-user payload (AdminUserDetailResource). The role-specific keys
 * are present only for the matching role; branch on `role`.
 */
export interface AdminUserDetail {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  status: UserStatus;
  specialty: string | null;
  bio: string | null;
  avatar: string | null;
  email_verified_at: string | null;
  created_at: string | null;
  /** Freelancer only. */
  subscriptions?: AdminUserSubscription[];
  subscriptions_summary?: AdminUserSubscriptionsSummary;
  recent_bookings?: AdminUserBooking[];
  /** Workspace-owner only. */
  workspaces?: AdminUserWorkspace[];
  documents?: AdminUserDocuments | null;
}

/** Full profile with role-specific detail for a single user. */
export async function getAdminUserDetail(id: string): Promise<AdminUserDetail> {
  const res = await serverFetch<ApiEnvelope<AdminUserDetail>>(
    `/admin/users/${id}`,
  );
  return res.data;
}

/* -------------------------------------------------------------------------- */
/*  Admin management (GET /admin/admins) — super-admin only                    */
/* -------------------------------------------------------------------------- */

/** A staff account as shown in the admin-management list (AdminResource). */
export interface ManagedAdmin {
  id: string;
  name: string;
  email: string;
  status: UserStatus;
  /** Spatie roles held by the account (e.g. `["super_admin"]`). */
  roles: AdminRole[];
  /** Whether the account holds the elevated super-admin role. */
  is_super_admin: boolean;
  /** Effective permission grant. */
  permissions: AdminPermission[];
  created_at: string | null;
}

export interface ManagedAdminFilters {
  status?: UserStatus;
  search?: string;
  per_page?: number;
  page?: number;
}

/** Filtered, paginated directory of admin accounts. */
export async function getManagedAdmins(
  filters: ManagedAdminFilters = {},
): Promise<Paginated<ManagedAdmin>> {
  const res = await serverFetch<ApiEnvelope<Paginated<ManagedAdmin>>>(
    `/admin/admins${toQuery(filters)}`,
  );
  return res.data;
}

/** All admin accounts flattened across pages (for the client-side table). */
export async function getAllManagedAdmins(
  filters: Omit<ManagedAdminFilters, "page"> = {},
): Promise<ManagedAdmin[]> {
  return collectPages((page) =>
    getManagedAdmins({ per_page: 200, ...filters, page }),
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
/*  Reports (GET /admin/reports)                                               */
/* -------------------------------------------------------------------------- */

/** One month bucket of admin-tracked revenue (decimal strings, not gateway money). */
export interface RevenueByMonth {
  /** `YYYY-MM`. */
  month: string;
  /** Sum of paid invoices that month, decimal string e.g. "1500.00". */
  paid: string;
  /** Sum of pending + overdue invoices that month, decimal string. */
  outstanding: string;
}

/** A count + summed amount pair for an invoice status bucket. */
export interface InvoiceStatusBucket {
  count: number;
  /** Decimal string of the summed invoice amounts in this bucket. */
  amount: string;
}

/** One month bucket of newly-created subscriptions. */
export interface SubscriptionsByMonth {
  /** `YYYY-MM`. */
  month: string;
  count: number;
}

/** A single row in the top-workspaces leaderboard. */
export interface TopWorkspace {
  workspace_id: string;
  name: string;
  /** Decimal string of paid revenue tracked against this workspace. */
  paid_total: string;
  active_subscriptions: number;
}

/** Aggregated analytics for the super-admin reports dashboard. */
export interface AdminReports {
  /** 12 continuous months, oldest first. */
  revenue_by_month: RevenueByMonth[];
  invoices_by_status: {
    paid: InvoiceStatusBucket;
    pending: InvoiceStatusBucket;
    overdue: InvoiceStatusBucket;
  };
  /** Count per subscription status. */
  subscriptions_by_status: Record<SubscriptionStatus, number>;
  /** 12 continuous months of new-subscription counts, oldest first. */
  subscriptions_by_month: SubscriptionsByMonth[];
  /** Up to 10 workspaces ranked by tracked paid revenue. */
  top_workspaces: TopWorkspace[];
  /** Count per workspace status. */
  workspaces_by_status: Record<WorkspaceStatus, number>;
  /** Count per user role. */
  users_by_role: Record<UserRole, number>;
}

/** Platform-wide aggregated analytics for the super-admin reports page. */
export async function getAdminReports(): Promise<AdminReports> {
  const res = await serverFetch<ApiEnvelope<AdminReports>>("/admin/reports");
  return res.data;
}

/* -------------------------------------------------------------------------- */
/*  Messaging settings (GET /admin/settings/messaging)                         */
/* -------------------------------------------------------------------------- */

/**
 * The platform's masked SMTP + SMS configuration. Secrets are never returned;
 * `has_password` / `has_credentials` flags signal whether one is stored.
 */
export async function getPlatformMessaging(): Promise<PlatformMessagingConfig> {
  const res = await serverFetch<ApiEnvelope<PlatformMessagingConfig>>(
    "/admin/settings/messaging",
  );
  return res.data;
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
