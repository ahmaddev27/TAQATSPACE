import { serverFetch } from "@/lib/api";
import type {
  ApiEnvelope,
  ContentKey,
  SiteContent,
  FaqContent,
  AboutContent,
  HowItWorksContent,
} from "@/lib/types";

/**
 * Maps each content key to the blob shape the endpoint returns/accepts. Keeps
 * `getContent`/`getAdminContent` strongly typed at every call site.
 */
export interface ContentByKey {
  site: SiteContent;
  faq: FaqContent;
  about: AboutContent;
  how_it_works: HowItWorksContent;
}

/**
 * The backend returns `data: []` (an empty array) when no content has been
 * saved yet. Normalise that — and any other non-object payload — to an empty
 * object so callers can rely on the blob shape unconditionally.
 */
function normalize<K extends ContentKey>(data: unknown): ContentByKey[K] {
  if (data && typeof data === "object" && !Array.isArray(data)) {
    return data as ContentByKey[K];
  }
  return {} as ContentByKey[K];
}

/**
 * Public site content (`GET /content/{key}`). Unauthenticated. Returns `{}` when
 * nothing is saved, so the caller falls back to the i18n defaults.
 */
export async function getContent<K extends ContentKey>(
  key: K,
): Promise<ContentByKey[K]> {
  const res = await serverFetch<ApiEnvelope<unknown>>(`/content/${key}`, {
    auth: false,
  });
  return normalize<K>(res.data);
}

/**
 * Admin site content (`GET /admin/content/{key}`). Authenticated; same shape as
 * the public endpoint. Used to seed the editor with the current draft.
 */
export async function getAdminContent<K extends ContentKey>(
  key: K,
): Promise<ContentByKey[K]> {
  const res = await serverFetch<ApiEnvelope<unknown>>(`/admin/content/${key}`);
  return normalize<K>(res.data);
}
