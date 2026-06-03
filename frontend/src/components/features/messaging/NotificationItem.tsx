"use client";

import type { CSSProperties } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import type { AppNotification } from "@/lib/api/notifications";
import { notifPresentation } from "./notif";
import { relativeTime } from "./time";

export interface NotificationItemProps {
  notification: AppNotification;
  /** Compact variant for the dropdown panel (smaller padding, no controls). */
  compact?: boolean;
  /** Click handler — marks read + navigates (provided by the parent). */
  onClick?: () => void;
  /** Optional per-item actions (full-list page only). */
  onMarkRead?: () => void;
  onDelete?: () => void;
}

/**
 * One notification row. Title resolves from the `messaging.notifications.types`
 * namespace; the body uses the backend `preview`/`workspace_name` when present.
 */
export function NotificationItem({
  notification,
  compact = false,
  onClick,
  onMarkRead,
  onDelete,
}: NotificationItemProps) {
  const t = useTranslations("messaging.notifications");
  const locale = useLocale();
  const { icon, tone } = notifPresentation(notification.type);
  const unread = !notification.is_read;

  const title = resolveTitle(t, notification.type);
  const body = resolveBody(notification);
  const time = relativeTime(notification.created_at, locale);

  const interactive = Boolean(onClick);

  const style: CSSProperties = {
    ...(compact ? { padding: 12, borderRadius: "var(--r-sm)" } : {}),
    ...(interactive ? { cursor: "pointer" } : {}),
  };

  return (
    <div
      className={`notif-item ${unread ? "unread" : ""}`.trim()}
      style={style}
      role={interactive ? "button" : undefined}
      tabIndex={interactive ? 0 : undefined}
      onClick={onClick}
      onKeyDown={
        interactive
          ? (e) => {
              if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                onClick?.();
              }
            }
          : undefined
      }
    >
      <span className={`act-ico ${tone}`}>
        <Icon name={icon} size={16} />
      </span>

      <div className="grow" style={{ minWidth: 0 }}>
        <div className="between" style={{ gap: 8 }}>
          <div style={{ fontWeight: 600, fontSize: "var(--fs-sm)" }}>{title}</div>
          <span
            className="muted-3"
            style={{ fontSize: "var(--fs-xs)", flex: "none" }}
          >
            {time}
          </span>
        </div>
        {body && (
          <div
            className="muted"
            style={{ fontSize: "var(--fs-sm)", marginTop: 3 }}
          >
            {body}
          </div>
        )}

        {!compact && (onMarkRead || onDelete) && (
          <div className="row" style={{ gap: 8, marginTop: 8 }}>
            {unread && onMarkRead && (
              <button
                type="button"
                className="btn btn-ghost btn-sm"
                onClick={(e) => {
                  e.stopPropagation();
                  onMarkRead();
                }}
              >
                <Icon name="check" size={14} />
                {t("markRead")}
              </button>
            )}
            {onDelete && (
              <button
                type="button"
                className="btn btn-ghost btn-sm"
                onClick={(e) => {
                  e.stopPropagation();
                  onDelete();
                }}
              >
                <Icon name="trash" size={14} />
                {t("delete")}
              </button>
            )}
          </div>
        )}
      </div>

      {unread && compact && <span className="notif-dot" />}
    </div>
  );
}

type TitleT = ReturnType<typeof useTranslations>;

/**
 * Resolve a notification's title from `types.<type>`, falling back to a generic
 * label for any unknown/future type. `t.has` avoids next-intl throwing on a
 * missing key.
 */
function resolveTitle(t: TitleT, type: string): string {
  const key = `types.${type}`;
  return t.has(key) ? t(key) : t("types.generic");
}

/** Body line: prefer the message preview, fall back to the workspace name. */
function resolveBody(n: AppNotification): string | null {
  const { preview, workspace_name } = n.data;
  if (typeof preview === "string" && preview.trim()) return preview;
  if (typeof workspace_name === "string" && workspace_name.trim()) {
    return workspace_name;
  }
  return null;
}
