import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type { AboutContent } from "@/lib/types/content";

/**
 * The backend returns `data: []` (an empty array) when no content has been
 * saved yet. Normalise that — and any other non-object payload — to an empty
 * content object so callers can rely on `AboutContent` shape unconditionally.
 */
function normalize(data: unknown): AboutContent {
  if (data && typeof data === "object" && !Array.isArray(data)) {
    return data as AboutContent;
  }
  return {};
}

/**
 * Public about page content (`GET /about-content`). Unauthenticated. Returns
 * `{}` when nothing is saved, so the caller falls back to the i18n defaults.
 * Image fields carry resolved display URLs added by the backend (`imageUrl`,
 * `imageSecondaryUrl`, per-section `imageUrl`).
 */
export async function getAboutContent(): Promise<AboutContent> {
  const res = await serverFetch<ApiEnvelope<unknown>>("/about-content", {
    auth: false,
  });
  return normalize(res.data);
}
