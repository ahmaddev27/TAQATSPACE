/**
 * Client-safe types for Finance v2 plans. Mirrors the Laravel `PlanResource`
 * shape and the store/update request rules.
 */
import type { SimpleMeta } from "@/lib/types";

export type PricingMethod =
  | "monthly"
  | "weekly"
  | "daily"
  | "hourly"
  | "per_gb"
  | "bundle";

export const PRICING_METHODS: PricingMethod[] = [
  "monthly",
  "weekly",
  "daily",
  "hourly",
  "per_gb",
  "bundle",
];

/** BR-FIN-04: only these methods can reserve a dedicated seat. */
export const DEDICATED_SEAT_METHODS: PricingMethod[] = ["monthly", "weekly", "bundle"];

export const METERED_METHODS: PricingMethod[] = ["hourly", "per_gb"];

export interface Plan {
  id: string;
  /** Fixed at creation. */
  code: string;
  name_ar: string;
  name_en: string;
  pricing_method: PricingMethod;
  /** Decimal strings, e.g. "150.50". */
  price: string;
  overage_price: string | null;
  /** Hours or GB, depending on the method. */
  included_quantity: string | null;
  validity_days: number | null;
  is_dedicated_seat: boolean;
  promo_price: string | null;
  promo_until: string | null;
  /** The price charged right now (promo price while the promotion runs). */
  effective_price: string;
  is_archived: boolean;
  archived_at: string | null;
  created_at: string | null;
}

export interface PlansResult {
  items: Plan[];
  meta: SimpleMeta;
}

export interface PlanInput {
  name_ar: string;
  name_en: string;
  pricing_method: PricingMethod;
  price: string;
  overage_price?: string | null;
  included_quantity?: string | null;
  validity_days?: number | null;
  is_dedicated_seat: boolean;
  promo_price?: string | null;
  promo_until?: string | null;
}

export type PlanCreateInput = PlanInput & { code: string };

export interface PlanRemoveResult {
  plan: Plan;
  deleted: boolean;
}

export type BillingMode = "prepaid" | "postpaid";

/** Body of `PUT /workspace/subscriptions/{id}/financial`; null clears a value. */
export interface SubscriptionTermsInput {
  plan_id: string | null;
  billing_mode: BillingMode;
  custom_price: string | null;
  contracted_hours: string | null;
  grace_days: number;
  auto_renew: boolean;
}
