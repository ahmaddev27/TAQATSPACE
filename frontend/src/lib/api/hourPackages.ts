import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, Paginated } from "@/lib/types";

/** A platform-level prepaid hour package (no workspace). */
export interface HourPackage {
  id: string;
  code: string;
  name_ar: string;
  name_en: string;
  hours: number;
  price: string;
  currency: string;
  /** Null means the hours never expire. */
  validity_days: number | null;
  is_active: boolean;
  sort_order: number;
}

export type HourPurchaseStatus =
  | "requested"
  | "active"
  | "exhausted"
  | "cancelled"
  | "expired";

/** One row of the purchase queue; hours and price are frozen at request time. */
export interface HourPurchase {
  id: string;
  status: HourPurchaseStatus;
  hours: number;
  price: string;
  currency: string;
  payment_method: string | null;
  receipt_url: string | null;
  paid_at: string | null;
  activated_at: string | null;
  expires_at: string | null;
  created_at: string | null;
  user?: { id: string; name: string; email: string };
  package?: HourPackage;
}

export type HourPurchaseStats = Record<HourPurchaseStatus, number>;

export interface HourPurchaseQueue extends Paginated<HourPurchase> {
  stats: HourPurchaseStats;
}

/** The full catalog, archived packages included. */
export async function getHourPackages(): Promise<HourPackage[]> {
  const res = await serverFetch<ApiEnvelope<HourPackage[]>>("/admin/hour-packages");
  return res.data;
}

/** The purchase queue (first page of 100) with per-status counts. */
export async function getHourPurchases(): Promise<HourPurchaseQueue> {
  const res = await serverFetch<ApiEnvelope<HourPurchaseQueue>>(
    "/admin/hour-purchases?per_page=100",
  );
  return res.data;
}
