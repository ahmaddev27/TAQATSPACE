"use client";

import {
  useCallback,
  useEffect,
  useRef,
  useState,
  useTransition,
} from "react";
import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { useAuth } from "@/components/providers/AuthProvider";
import { useRouter } from "@/i18n/navigation";
import { notifHref } from "@/components/features/messaging/notif";
import {
  markNotificationsRead,
} from "@/lib/actions/messaging";
import type {
  AppNotification,
  NotificationListResult,
} from "@/lib/api/notifications";
import { NotificationPanel } from "./NotificationPanel";
import "@/components/features/messaging/messaging.css";

/** How often the bell re-polls the notifications proxy, in ms. */
const POLL_INTERVAL = 30_000;
const PANEL_SIZE = 8;

/**
 * Topbar notification bell. Polls the `/api/notifications` proxy on an interval
 * to keep the unread badge and recent feed current. Realtime is deferred — when
 * a Pusher channel is wired up later, it can call `refresh()` on each event to
 * replace polling without changing this component's surface.
 */
export function NotificationBell() {
  const t = useTranslations("messaging.notifications");
  const tCommon = useTranslations("common");
  const { role } = useAuth();
  const router = useRouter();

  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<AppNotification[]>([]);
  const [unread, setUnread] = useState(0);
  const [, startTransition] = useTransition();

  const wrapRef = useRef<HTMLDivElement>(null);
  const mounted = useRef(true);

  const hrefRole: "owner" | "freelancer" =
    role === "workspace_owner" ? "owner" : "freelancer";

  const refresh = useCallback(async () => {
    try {
      const res = await fetch(`/api/notifications?per_page=${PANEL_SIZE}`, {
        credentials: "include",
        cache: "no-store",
      });
      if (!res.ok) return;
      const data = (await res.json()) as NotificationListResult;
      if (!mounted.current) return;
      setItems(data.notifications);
      setUnread(data.meta.unread_count);
    } catch {
      // Network hiccup — keep the last good state and retry next tick.
    }
  }, []);

  // Poll while the bell is mounted (every role with a dashboard has it). This is
  // an external-system subscription: `refresh` only calls setState after its
  // `await fetch` resolves, never synchronously within the effect body.
  useEffect(() => {
    mounted.current = true;
    // eslint-disable-next-line react-hooks/set-state-in-effect -- async poll, setState runs post-await
    void refresh();
    const id = window.setInterval(() => void refresh(), POLL_INTERVAL);
    return () => {
      mounted.current = false;
      window.clearInterval(id);
    };
  }, [refresh]);

  // Close on outside click / Escape.
  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(false);
    };
    document.addEventListener("mousedown", onDown);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDown);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const markAllRead = useCallback(() => {
    // Optimistic: clear locally, then persist.
    setItems((list) => list.map((n) => ({ ...n, is_read: true, read_at: new Date().toISOString() })));
    setUnread(0);
    startTransition(async () => {
      await markNotificationsRead("all");
      void refresh();
    });
  }, [refresh]);

  const handleItemClick = useCallback(
    (n: AppNotification) => {
      const href = notifHref(n.data, hrefRole);
      if (!n.is_read) {
        setItems((list) =>
          list.map((x) =>
            x.id === n.id
              ? { ...x, is_read: true, read_at: new Date().toISOString() }
              : x,
          ),
        );
        setUnread((c) => Math.max(0, c - 1));
        startTransition(async () => {
          await markNotificationsRead([n.id]);
          void refresh();
        });
      }
      setOpen(false);
      if (href) router.push(href);
    },
    [hrefRole, router, refresh],
  );

  const badgeLabel = unread > 99 ? "99+" : String(unread);

  return (
    <div className="notif-bell" ref={wrapRef}>
      <button
        type="button"
        className="icon-btn"
        aria-label={t("bellLabel")}
        aria-haspopup="dialog"
        aria-expanded={open}
        onClick={() => setOpen((o) => !o)}
      >
        <Icon name="bell" />
        {unread > 0 && (
          <span className="notif-bell-badge tnum">{badgeLabel}</span>
        )}
      </button>

      {open && (
        <>
          {/* Dims the screen behind the mobile drawer; tapping it closes (the
              full-height drawer is CSS-only, hidden on desktop). */}
          <button
            type="button"
            className="notif-backdrop"
            aria-label={tCommon("close")}
            onClick={() => setOpen(false)}
          />
          <div className="notif-pop">
            <NotificationPanel
              notifications={items}
              unreadCount={unread}
              hrefRole={hrefRole}
              onClose={() => setOpen(false)}
              onMarkAllRead={markAllRead}
              onItemClick={handleItemClick}
            />
          </div>
        </>
      )}
    </div>
  );
}
