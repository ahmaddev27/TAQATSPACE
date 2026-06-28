"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { useToast } from "@/components/providers/ToastProvider";
import {
  deleteNotification,
  markNotificationsRead,
} from "@/lib/actions/messaging";
import type {
  AppNotification,
  NotificationListResult,
  NotificationMeta,
} from "@/lib/api/notifications";
import { NotificationItem } from "./NotificationItem";
import { notifHref } from "./notif";
import "./messaging.css";

type Filter = "all" | "unread";

/** Page size for "Load more" — matches the server pages' initial fetch. */
const PAGE_SIZE = 50;

export interface NotificationsViewProps {
  initial: AppNotification[];
  /** First-page meta (pagination + unread total) from the server page. */
  initialMeta: NotificationMeta;
  /** Scopes per-item destinations + matches the recipient role. */
  role: "owner" | "freelancer";
}

/**
 * Full notification feed with an all/unread segmented filter, per-item mark-read
 * and delete controls, and a "mark all read" header action. Server pages hydrate
 * it; mutations update local state optimistically and persist via Server Actions.
 */
export function NotificationsView({
  initial,
  initialMeta,
  role,
}: NotificationsViewProps) {
  const t = useTranslations("messaging.notifications");
  const { toast } = useToast();
  const router = useRouter();
  const [, startTransition] = useTransition();

  const [items, setItems] = useState<AppNotification[]>(initial);
  const [unread, setUnread] = useState(initialMeta.unread_count);
  const [filter, setFilter] = useState<Filter>("all");

  // Pagination state so the feed can show every notification, not just page 1.
  const [page, setPage] = useState(initialMeta.current_page);
  const [hasMore, setHasMore] = useState(
    initialMeta.current_page < initialMeta.last_page,
  );
  const [loadingMore, setLoadingMore] = useState(false);

  const visible = useMemo(
    () => (filter === "unread" ? items.filter((n) => !n.is_read) : items),
    [items, filter],
  );

  const loadMore = async () => {
    if (loadingMore || !hasMore) return;
    setLoadingMore(true);
    try {
      const next = page + 1;
      const res = await fetch(
        `/api/notifications?page=${next}&per_page=${PAGE_SIZE}`,
        { cache: "no-store" },
      );
      if (!res.ok) throw new Error("load_failed");
      const data = (await res.json()) as NotificationListResult;
      // Dedupe by id so a notification arriving between pages isn't shown twice.
      setItems((list) => {
        const seen = new Set(list.map((n) => n.id));
        return [...list, ...data.notifications.filter((n) => !seen.has(n.id))];
      });
      setPage(data.meta.current_page);
      setHasMore(data.meta.current_page < data.meta.last_page);
    } catch {
      toast({ tone: "err", title: t("actionFailed") });
    } finally {
      setLoadingMore(false);
    }
  };

  const markOne = (n: AppNotification) => {
    if (n.is_read) return;
    setItems((list) =>
      list.map((x) =>
        x.id === n.id
          ? { ...x, is_read: true, read_at: new Date().toISOString() }
          : x,
      ),
    );
    setUnread((c) => Math.max(0, c - 1));
    startTransition(async () => {
      const res = await markNotificationsRead([n.id]);
      if (!res.ok) toast({ tone: "err", title: t("actionFailed") });
    });
  };

  const markAll = () => {
    if (unread === 0) return;
    setItems((list) =>
      list.map((n) => ({ ...n, is_read: true, read_at: new Date().toISOString() })),
    );
    setUnread(0);
    startTransition(async () => {
      const res = await markNotificationsRead("all");
      if (res.ok) toast({ tone: "ok", title: t("allReadToast") });
      else toast({ tone: "err", title: t("actionFailed") });
    });
  };

  const remove = (n: AppNotification) => {
    setItems((list) => list.filter((x) => x.id !== n.id));
    if (!n.is_read) setUnread((c) => Math.max(0, c - 1));
    startTransition(async () => {
      const res = await deleteNotification(n.id);
      if (res.ok) toast({ tone: "ok", title: t("deletedToast") });
      else toast({ tone: "err", title: t("actionFailed") });
    });
  };

  const open = (n: AppNotification) => {
    markOne(n);
    const href = notifHref(n.data, role);
    if (href) router.push(href);
  };

  return (
    <div className="stack" style={{ gap: 16 }}>
      <div className="between wrap" style={{ gap: 12 }}>
        <div className="seg">
          <button
            type="button"
            className={filter === "all" ? "active" : ""}
            onClick={() => setFilter("all")}
          >
            {t("filterAll")}
          </button>
          <button
            type="button"
            className={filter === "unread" ? "active" : ""}
            onClick={() => setFilter("unread")}
          >
            {t("filterUnread")}
            {unread > 0 ? ` (${unread})` : ""}
          </button>
        </div>

        <button
          type="button"
          className="btn btn-ghost btn-sm"
          onClick={markAll}
          disabled={unread === 0}
        >
          <Icon name="check" size={16} />
          {t("markAllRead")}
        </button>
      </div>

      {visible.length === 0 ? (
        <div className="empty-state">
          <div>
            <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
              {t("emptyTitle")}
            </div>
            <div style={{ fontSize: "var(--fs-sm)" }}>
              {filter === "unread" ? t("emptyUnread") : t("emptyBody")}
            </div>
          </div>
        </div>
      ) : (
        <div className="notif-list">
          {visible.map((n) => (
            <NotificationItem
              key={n.id}
              notification={n}
              onClick={() => open(n)}
              onMarkRead={() => markOne(n)}
              onDelete={() => remove(n)}
            />
          ))}
        </div>
      )}

      {hasMore && (
        <div className="row" style={{ justifyContent: "center" }}>
          <button
            type="button"
            className="btn btn-ghost btn-sm"
            onClick={loadMore}
            disabled={loadingMore}
          >
            {loadingMore ? t("loadingMore") : t("loadMore")}
          </button>
        </div>
      )}
    </div>
  );
}
