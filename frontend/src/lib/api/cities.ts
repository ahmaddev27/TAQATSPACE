import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, City } from "@/lib/types";

/**
 * Public list of active cities (`GET /cities`, unauthenticated). Used to seed
 * the dynamic city `<Select>` in the owner forms and the explore filter facet.
 * Returns an empty list on any error so callers never crash on a transient API
 * failure — the form/filter simply renders no city options.
 */
export async function getCities(): Promise<City[]> {
  try {
    const res = await serverFetch<ApiEnvelope<City[]>>("/cities", {
      auth: false,
    });
    return res.data;
  } catch {
    return [];
  }
}

/**
 * Admin list of all cities with their workspace usage count
 * (`GET /admin/cities`, gated `manage_content`).
 */
export async function getAdminCities(): Promise<City[]> {
  const res = await serverFetch<ApiEnvelope<City[]>>("/admin/cities");
  return res.data;
}
