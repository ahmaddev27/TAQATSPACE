import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, Branding } from "@/lib/types";

/**
 * The backend returns `data: []` (an empty array) when no branding has been
 * saved yet. Normalise that — and any other non-object payload — to an empty
 * object so callers can rely on the `Branding` shape unconditionally.
 */
function normalize(data: unknown): Branding {
  if (data && typeof data === "object" && !Array.isArray(data)) {
    return data as Branding;
  }
  return {};
}

/**
 * Public site branding (`GET /branding`). Unauthenticated. Returns `{}` when
 * nothing is saved, so the caller falls back to the built-in branding.
 */
export async function getBranding(): Promise<Branding> {
  const res = await serverFetch<ApiEnvelope<unknown>>("/branding", {
    auth: false,
  });
  return normalize(res.data);
}

/**
 * Admin site branding (`GET /admin/branding`). Authenticated; same shape as the
 * public endpoint. Used to seed the editor with the current draft.
 */
export async function getAdminBranding(): Promise<Branding> {
  const res = await serverFetch<ApiEnvelope<unknown>>("/admin/branding");
  return normalize(res.data);
}
