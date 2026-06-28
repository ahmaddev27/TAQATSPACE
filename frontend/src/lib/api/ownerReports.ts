import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";

export interface AgingBucket {
  key: "current" | "d1_30" | "d31_60" | "d61_90" | "d90_plus";
  count: number;
  amount: string;
}

export interface ProfitLossMonth {
  month: string;
  revenue: string;
  expenses: string;
  net: string;
}

export interface PackageUptakeRow {
  name: string;
  price: string;
  members: number;
}

export interface OwnerReports {
  aging: AgingBucket[];
  aging_total: string;
  profit_loss: ProfitLossMonth[];
  package_uptake: PackageUptakeRow[];
}

/** Deeper owner reports: collections aging, monthly P&L, and package uptake. */
export async function getOwnerReports(): Promise<OwnerReports> {
  const res = await serverFetch<ApiEnvelope<OwnerReports>>("/workspace/reports");
  return res.data;
}
