"use client";

import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";

/**
 * Home-page notification bell with an unread badge. The notifications feed
 * itself is a later milestone; this surfaces the unread count from the member
 * dashboard summary so the freelancer knows there is something to read.
 */
export function NotificationBell({ unread }: { unread: number }) {
  const t = useTranslations("freelancer.home");
  const hasUnread = unread > 0;

  return (
    <button
      type="button"
      className="icon-btn"
      style={{ position: "relative" }}
      aria-label={hasUnread ? t("unreadBell", { count: unread }) : undefined}
      title={hasUnread ? t("unreadBell", { count: unread }) : undefined}
    >
      <Icon name="bell" />
      {hasUnread && (
        <span
          className="badge badge-danger"
          style={{
            position: "absolute",
            top: -6,
            insetInlineEnd: -6,
            minWidth: 18,
            height: 18,
            padding: "0 5px",
            borderRadius: 999,
            fontSize: "var(--fs-xs)",
            justifyContent: "center",
          }}
        >
          {unread > 9 ? "9+" : unread}
        </span>
      )}
    </button>
  );
}
