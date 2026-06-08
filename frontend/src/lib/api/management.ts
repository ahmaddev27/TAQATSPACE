import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type {
  ExpensesParams,
  ExpensesResult,
  ResourcesParams,
  ResourcesResult,
} from "@/lib/types/management";

/**
 * Server-only data readers for the owner management module. This module imports
 * `serverFetch` (next/headers), so it must only be used from Server Components.
 * Client components import the types/constants from `@/lib/types/management`.
 */

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
