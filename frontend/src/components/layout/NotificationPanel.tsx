"use client";

import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { Link } from "@/i18n/navigation";
import { NotificationItem } from "@/components/features/messaging/NotificationItem";
import type { AppNotification } from "@/lib/api/notifications";

export interface NotificationPanelProps {
  notifications: AppNotification[];
  unreadCount: number;
  /** Drives the "view all" link and per-item destinations. */
  hrefRole: "owner" | "freelancer";
  onClose: () => void;
  onMarkAllRead: () => void;
  /** Mark one read then navigate to its destination. */
  onItemClick: (n: AppNotification) => void;
}

/**
 * Dropdown surface for the bell: a short, scrollable list of recent
 * notifications with a "mark all read" header and a "view all" footer.
 */
export function NotificationPanel({
  notifications,
  unreadCount,
  hrefRole,
  onClose,
  onMarkAllRead,
  onItemClick,
}: NotificationPanelProps) {
  const t = useTranslations("messaging.notifications");
  const viewAllHref =
    hrefRole === "owner" ? "/owner/notifications" : "/freelancer/notifications";

  return (
    <div className="notif-panel" role="dialog" aria-label={t("panelTitle")}>
      <div className="notif-panel-head">
        <div className="row" style={{ gap: 8, alignItems: "center" }}>
          <strong style={{ fontSize: "var(--fs-sm)" }}>{t("panelTitle")}</strong>
          {unreadCount > 0 && (
            <span className="badge badge-soft">{unreadCount}</span>
          )}
        </div>
        <button
          type="button"
          className="btn btn-ghost btn-sm"
          onClick={onMarkAllRead}
          disabled={unreadCount === 0}
        >
          <Icon name="check" size={14} />
          {t("markAllRead")}
        </button>
      </div>

      <div className="notif-panel-scroll">
        {notifications.length === 0 ? (
          <div className="empty-state" style={{ padding: 24 }}>
            <div>
              <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                {t("emptyTitle")}
              </div>
              <div style={{ fontSize: "var(--fs-sm)" }}>{t("emptyBody")}</div>
            </div>
          </div>
        ) : (
          <div className="stack" style={{ gap: 6 }}>
            {notifications.map((n) => (
              <NotificationItem
                key={n.id}
                notification={n}
                compact
                onClick={() => onItemClick(n)}
              />
            ))}
          </div>
        )}
      </div>

      <div className="notif-panel-foot">
        <Link
          href={viewAllHref}
          className="btn btn-secondary btn-sm btn-block"
          onClick={onClose}
        >
          {t("viewAll")}
        </Link>
      </div>
    </div>
  );
}
