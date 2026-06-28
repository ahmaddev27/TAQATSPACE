import { serverFetch } from "@/lib/api";
import type {
  ApiEnvelope,
  PlanType,
  SimpleMeta,
  SubscriptionStatus,
} from "@/lib/types";

/* -------------------------------------------------------------------------- */
/*  Types (mirror App\Http\Resources\OwnerSubscriptionResource)               */
/* -------------------------------------------------------------------------- */

/** The member nested on an owner subscription row. */
export interface OwnerSubscriptionMember {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  specialty: string | null;
  avatar: string | null;
}

/** A subscription as seen by the workspace owner. */
export interface OwnerSubscription {
  id: string;
  member: OwnerSubscriptionMember;
  plan_type: PlanType;
  /** Decimal string, e.g. "120.00". */
  monthly_price: string;
  /** The member's internet package name(s) in this workspace, or null. */
  internet_package: string | null;
  /** Decimal string — total price of the internet package(s). */
  package_price: string;
  /** Decimal string — monthly_price + package_price. */
  total_price: string;
  /** True when the member has been issued internet credentials. */
  has_internet: boolean;
  seat_number: string | null;
  start_date: string | null;
  end_date: string | null;
  status: SubscriptionStatus;
  cancelled_at: string | null;
  created_at: string | null;
}

/** Owner subscriptions listing payload: `{ subscriptions, meta }`. */
export interface OwnerSubscriptionsResult {
  subscriptions: OwnerSubscription[];
  meta: SimpleMeta;
}

export interface OwnerSubscriptionsParams {
  status?: SubscriptionStatus | "all";
  search?: string;
  sort?: "start_date" | "end_date" | "name" | "status" | "monthly_price";
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
}

/* -------------------------------------------------------------------------- */
/*  Queries                                                                    */
/* -------------------------------------------------------------------------- */

function toQuery(params: OwnerSubscriptionsParams): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/** Owner: subscriptions for THIS workspace only. */
export async function ownerSubscriptions(
  params: OwnerSubscriptionsParams = {},
): Promise<OwnerSubscriptionsResult> {
  const res = await serverFetch<ApiEnvelope<OwnerSubscriptionsResult>>(
    `/workspace/subscriptions${toQuery(params)}`,
  );
  return res.data;
}
