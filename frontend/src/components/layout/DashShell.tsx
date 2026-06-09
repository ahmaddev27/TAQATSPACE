"use client";

import {
  Suspense,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { useTranslations } from "next-intl";
import { usePathname } from "next/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { PushRegistrar } from "@/components/providers/PushRegistrar";
import type { UserRole } from "@/lib/types/auth";
import { isFirebaseConfigured } from "@/lib/firebase/app";
import { useChatUnread } from "@/lib/firebase/useChatUnread";
import {
  ROLE_NAV,
  applyChatBadge,
  filterNavByPermissions,
  filterNavByRealtime,
} from "./nav-config";
import { Sidebar } from "./Sidebar";
import { TopNav } from "./TopNav";
import { NavProgress } from "./NavProgress";

export interface DashShellProps {
  role: UserRole;
  userName: string;
  /** First grapheme of the user's name, for the avatar. */
  avatarInitial: string;
  children: ReactNode;
}

const NAV_WIDTH = "var(--nav-w)";
const COLLAPSED_WIDTH = "72px";

/**
 * Must match the `max-width` breakpoint that switches the sidebar to an
 * off-canvas drawer in `styles/dash.css`. Kept in one place so the JS toggle
 * and the CSS layout never disagree.
 */
const MOBILE_BREAKPOINT = 860;

/**
 * Dashboard chrome: collapsible sidebar + sticky topbar + scrollable content.
 *
 * Two independent states drive the sidebar:
 *  - `collapsed`  → desktop icon-only rail (≥ breakpoint).
 *  - `mobileOpen` → off-canvas drawer (< breakpoint), hidden by default.
 *
 * Server layouts pass the authenticated user's role + name; all interaction is
 * handled here on the client.
 */
export function DashShell({
  role,
  userName,
  avatarInitial,
  children,
}: DashShellProps) {
  const t = useTranslations("nav");
  const { logout, user } = useAuth();
  const pathname = usePathname();
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const lastPathRef = useRef(pathname);

  // Firebase config is fixed at build time, so realtime availability is stable.
  const firebaseReady = isFirebaseConfigured();

  // Live count of conversations with unseen incoming messages, for the Chat badge.
  const chatUnread = useChatUnread(user?.id);

  // Hide permission-gated items (e.g. admin management) the user cannot access,
  // then collapse the Chat/Messages duplication to a single 1:1 surface based on
  // whether realtime chat is available, and finally stamp the unread-chat badge.
  // The auth payload carries the admin account's effective permission grant.
  const nav = useMemo(
    () =>
      applyChatBadge(
        filterNavByRealtime(
          filterNavByPermissions(
            ROLE_NAV[role],
            user?.permissions,
            user?.is_super_admin,
          ),
          firebaseReady,
        ),
        chatUnread,
      ),
    [role, user?.permissions, user?.is_super_admin, firebaseReady, chatUnread],
  );

  const closeMobile = useCallback(() => setMobileOpen(false), []);

  // The single top-bar control: collapse the rail on desktop, open the drawer
  // on mobile. Branching on the live viewport keeps one button serving both.
  const onMenu = useCallback(() => {
    const isMobile =
      typeof window !== "undefined" &&
      window.innerWidth <= MOBILE_BREAKPOINT;

    if (isMobile) {
      setMobileOpen((open) => !open);
    } else {
      setCollapsed((c) => !c);
    }
  }, []);

  const handleLogout = useCallback(() => {
    setMobileOpen(false);
    void logout();
  }, [logout]);

  // Close the drawer on any route change — covers link clicks and programmatic
  // navigation alike, so the drawer never lingers over the new page. Guarded by
  // a ref so state is only touched when the path actually changes.
  useEffect(() => {
    if (lastPathRef.current !== pathname) {
      lastPathRef.current = pathname;
      setMobileOpen(false);
    }
  }, [pathname]);

  // Escape closes the drawer; lock body scroll while it's open so the page
  // behind doesn't scroll under the overlay.
  useEffect(() => {
    if (!mobileOpen) return;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") setMobileOpen(false);
    };
    document.addEventListener("keydown", onKeyDown);

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [mobileOpen]);

  return (
    <div
      className="dash"
      style={{
        gridTemplateColumns: collapsed
          ? `${COLLAPSED_WIDTH} 1fr`
          : `${NAV_WIDTH} 1fr`,
      }}
    >
      <Suspense fallback={null}>
        <NavProgress />
      </Suspense>

      {/* FCM web-push lifecycle (no-op until Firebase env is configured). */}
      <PushRegistrar />

      {mobileOpen && (
        <div className="nav-backdrop" onClick={closeMobile} aria-hidden="true" />
      )}

      <Sidebar
        nav={nav}
        collapsed={collapsed}
        mobileOpen={mobileOpen}
        onNavigate={closeMobile}
        onLogout={handleLogout}
      />

      <div className="dash-main">
        <TopNav
          role={role}
          userName={userName}
          roleLabel={t(nav.roleLabelKey)}
          avatarInitial={avatarInitial}
          mobileOpen={mobileOpen}
          onMenu={onMenu}
          onLogout={handleLogout}
        />
        <div className="dash-scroll">{children}</div>
      </div>
    </div>
  );
}
