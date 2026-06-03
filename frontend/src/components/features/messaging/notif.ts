import type { IconName } from "@/components/ui/Icon";
import type { NotificationData } from "@/lib/api/notifications";

/** Visual tone matching the `.act-ico` modifier classes in dash.css. */
export type NotifTone = "ok" | "info" | "warn";

export interface NotifPresentation {
  icon: IconName;
  tone: NotifTone;
}

/** Map a notification type to its icon + tone. Unknown types fall back to bell. */
export function notifPresentation(type: string): NotifPresentation {
  switch (type) {
    case "booking_approved":
    case "seat_assigned":
      return { icon: "checkCircle", tone: "ok" };
    case "booking_rejected":
      return { icon: "xCircle", tone: "warn" };
    case "invoice_paid":
      return { icon: "wallet", tone: "ok" };
    case "invoice_overdue":
    case "invoice_reminder":
      return { icon: "alert", tone: "warn" };
    case "invoice_created":
      return { icon: "receipt", tone: "info" };
    case "new_announcement":
      return { icon: "megaphone", tone: "warn" };
    case "new_message":
      return { icon: "chat", tone: "info" };
    case "new_workspace_registration":
      return { icon: "building", tone: "info" };
    default:
      return { icon: "bell", tone: "info" };
  }
}

/**
 * Resolve the in-app destination for a notification, scoped to the recipient's
 * role so freelancer notifications never link into owner-only routes. Returns a
 * locale-relative path (the locale prefix is added by `Link`), or `null` when
 * there is no meaningful destination.
 */
export function notifHref(
  data: NotificationData,
  role: "owner" | "freelancer",
): string | null {
  const base = role === "owner" ? "/owner" : "/freelancer";

  switch (data.type) {
    case "booking_approved":
    case "booking_rejected":
      return role === "owner" ? `${base}/requests` : `${base}`;
    case "invoice_created":
    case "invoice_overdue":
    case "invoice_paid":
    case "invoice_reminder":
      return `${base}/invoices`;
    case "new_announcement":
      return role === "owner" ? `${base}/announcements` : `${base}`;
    case "new_message":
      return role === "owner" && data.sender_id
        ? `${base}/messages`
        : `${base}`;
    case "seat_assigned":
      return role === "owner" ? `${base}/seats` : `${base}`;
    default:
      return null;
  }
}
