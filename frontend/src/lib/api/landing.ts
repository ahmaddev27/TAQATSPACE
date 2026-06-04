import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, LandingContent } from "@/lib/types";

/**
 * The backend returns `data: []` (an empty array) when no content has been
 * saved yet. Normalise that — and any other non-object payload — to an empty
 * content object so callers can rely on `LandingContent` shape unconditionally.
 */
function normalize(data: unknown): LandingContent {
  if (data && typeof data === "object" && !Array.isArray(data)) {
    return data as LandingContent;
  }
  return {};
}

/**
 * Public landing content (`GET /landing`). Unauthenticated. Returns `{}` when
 * nothing is saved, so the caller falls back to the i18n defaults.
 */
export async function getLanding(): Promise<LandingContent> {
  const res = await serverFetch<ApiEnvelope<unknown>>("/landing", {
    auth: false,
  });
  return normalize(res.data);
}

/**
 * Admin landing content (`GET /admin/landing`). Authenticated; same shape as
 * the public endpoint. Used to seed the editor with the current draft.
 */
export async function getAdminLanding(): Promise<LandingContent> {
  const res = await serverFetch<ApiEnvelope<unknown>>("/admin/landing");
  return normalize(res.data);
}
