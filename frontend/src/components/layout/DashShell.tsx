"use client";

import { useCallback, useState, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import { useAuth } from "@/components/providers/AuthProvider";
import type { UserRole } from "@/lib/types/auth";
import { ROLE_NAV } from "./nav-config";
import { Sidebar } from "./Sidebar";
import { TopNav } from "./TopNav";

export interface DashShellProps {
  role: UserRole;
  userName: string;
  /** First grapheme of the user's name, for the avatar. */
  avatarInitial: string;
  children: ReactNode;
}

const NAV_WIDTH = "var(--nav-w)";
const COLLAPSED_WIDTH = "72px";
const MOBILE_BREAKPOINT = 860;

/**
 * Dashboard chrome: collapsible sidebar + sticky topbar + scrollable content.
 * Server layouts pass the authenticated user's role + name; interaction
 * (collapse, off-canvas, logout) is handled here on the client.
 */
export function DashShell({
  role,
  userName,
  avatarInitial,
  children,
}: DashShellProps) {
  const t = useTranslations("nav");
  const { logout } = useAuth();
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  const nav = ROLE_NAV[role];

  const onMenu = useCallback(() => {
    if (
      typeof window !== "undefined" &&
      window.innerWidth <= MOBILE_BREAKPOINT
    ) {
      setCollapsed(false);
      setMobileOpen((open) => !open);
    } else {
      setCollapsed((c) => !c);
    }
  }, []);

  const closeMobile = useCallback(() => setMobileOpen(false), []);
  const handleLogout = useCallback(() => {
    setMobileOpen(false);
    void logout();
  }, [logout]);

  return (
    <div
      className="dash"
      style={{
        gridTemplateColumns: collapsed
          ? `${COLLAPSED_WIDTH} 1fr`
          : `${NAV_WIDTH} 1fr`,
      }}
    >
      {mobileOpen && <div className="nav-backdrop" onClick={closeMobile} />}

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
          onMenu={onMenu}
          onLogout={handleLogout}
        />
        <div className="dash-scroll">{children}</div>
      </div>
    </div>
  );
}
