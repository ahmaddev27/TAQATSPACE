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
    case "new_booking_request":
      return { icon: "inbox", tone: "info" };
    case "invoice_paid":
      return { icon: "wallet", tone: "ok" };
    case "invoice_overdue":
    case "invoice_reminder":
      return { icon: "alert", tone: "warn" };
    case "invoice_created":
      return { icon: "receipt", tone: "info" };
    case "invoice_receipt_submitted":
      return { icon: "receipt", tone: "warn" };
    case "subscription_expiring":
      return { icon: "card", tone: "warn" };
    case "new_announcement":
      return { icon: "megaphone", tone: "warn" };
    case "new_message":
    case "new_chat_message":
      return { icon: "chat", tone: "info" };
    case "new_review":
      return { icon: "checkCircle", tone: "ok" };
    case "new_workspace_registration":
    case "workspace_status_changed":
      return { icon: "building", tone: "info" };
    case "account_status_changed":
      return { icon: "user", tone: "info" };
    case "new_contact_message":
      return { icon: "mail", tone: "info" };
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
    case "new_booking_request":
      return `${base}/requests`;
    case "invoice_created":
    case "invoice_overdue":
    case "invoice_paid":
    case "invoice_reminder":
    case "invoice_receipt_submitted":
      return `${base}/invoices`;
    case "subscription_expiring":
      return role === "owner" ? `${base}/subscriptions` : `${base}/subscription`;
    case "new_announcement":
      return role === "owner" ? `${base}/announcements` : `${base}`;
    case "new_message":
      return role === "owner" && data.sender_id
        ? `${base}/messages`
        : `${base}`;
    case "new_chat_message":
      return `${base}/chat`;
    case "seat_assigned":
      return role === "owner" ? `${base}/seats` : `${base}`;
    default:
      return null;
  }
}
