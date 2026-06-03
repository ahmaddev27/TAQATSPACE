import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";

/* -------------------------------------------------------------------------- */
/*  Types                                                                      */
/* -------------------------------------------------------------------------- */

/**
 * Notification `type` discriminators, mirroring the `type` key persisted by the
 * backend `App\Notifications\*` classes. Unknown future types fall back to a
 * generic bell in the UI, so this list is permissive (string union, not enum).
 */
export type NotificationType =
  | "booking_approved"
  | "booking_rejected"
  | "invoice_created"
  | "invoice_overdue"
  | "invoice_paid"
  | "invoice_reminder"
  | "new_announcement"
  | "new_message"
  | "new_workspace_registration"
  | "seat_assigned";

/**
 * Free-form payload stored with every database notification. Only the keys the
 * UI reads are typed; the rest are tolerated via the index signature so new
 * backend keys never break parsing.
 */
export interface NotificationData {
  type: NotificationType | string;
  booking_request_id?: string;
  invoice_id?: string;
  announcement_id?: string;
  message_id?: string;
  sender_id?: string;
  seat_id?: string;
  workspace_id?: string;
  workspace_name?: string;
  is_broadcast?: boolean;
  preview?: string;
  [key: string]: unknown;
}

export interface AppNotification {
  id: string;
  type: NotificationType | string;
  data: NotificationData;
  read_at: string | null;
  is_read: boolean;
  created_at: string | null;
}

export interface NotificationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  unread_count: number;
}

export interface NotificationListResult {
  notifications: AppNotification[];
  meta: NotificationMeta;
}

export interface ListNotificationsParams {
  /** When true, only unread notifications are returned. */
  unread?: boolean;
  per_page?: number;
  page?: number;
}

/* -------------------------------------------------------------------------- */
/*  Reads                                                                      */
/* -------------------------------------------------------------------------- */

function buildQuery(params: ListNotificationsParams): string {
  const search = new URLSearchParams();
  if (params.unread) search.set("unread", "1");
  if (params.per_page != null) search.set("per_page", String(params.per_page));
  if (params.page != null) search.set("page", String(params.page));
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/**
 * The authenticated user's notifications, newest first. Response shape is
 * `{ notifications, meta: { ..., unread_count } }`.
 */
export async function listNotifications(
  params: ListNotificationsParams = {},
): Promise<NotificationListResult> {
  const res = await serverFetch<ApiEnvelope<NotificationListResult>>(
    `/notifications${buildQuery(params)}`,
  );
  return res.data;
}
