import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";

/* -------------------------------------------------------------------------- */
/*  Types (owned by this area — mirror App\Http\Resources\AnnouncementResource) */
/* -------------------------------------------------------------------------- */

/** Owner-creatable types. The platform-only `system` type is never authored here. */
export type AnnouncementType = "offer" | "info" | "alert";

/** Lifecycle state derived server-side from publish/expiry timestamps. */
export type AnnouncementStatus = "draft" | "live" | "expired";

export interface Announcement {
  id: string;
  workspace_id: string;
  type: AnnouncementType;
  title: string;
  body: string;
  status: AnnouncementStatus;
  /** ISO-8601; `null` while a draft. A future value also reads as a draft. */
  published_at: string | null;
  /** ISO-8601; `null` when the announcement never expires. */
  expires_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

/** Request body for create/update (timestamps are ISO-8601 strings). */
export interface AnnouncementInput {
  type: AnnouncementType;
  title: string;
  body: string;
  published_at?: string | null;
  expires_at?: string | null;
}

/* -------------------------------------------------------------------------- */
/*  Reads                                                                      */
/* -------------------------------------------------------------------------- */

/**
 * Owner: every announcement of the owner's workspace, including drafts and
 * expired ones. Response shape is `{ announcements }` (a named collection,
 * not the standard resource paginator).
 */
export async function listAnnouncements(): Promise<Announcement[]> {
  const res = await serverFetch<ApiEnvelope<{ announcements: Announcement[] }>>(
    "/workspace/announcements",
  );
  return res.data.announcements;
}
