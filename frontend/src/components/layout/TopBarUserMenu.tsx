"use client";

import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { usePathname } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { Avatar } from "@/components/ui/Avatar";
import { useAuth } from "@/components/providers/AuthProvider";
import { dashboardFor } from "@/lib/auth";
import type { UserRole } from "@/lib/types/auth";

export interface TopBarUserMenuProps {
  /** Server-rendered name, used until the client auth context hydrates. */
  userName: string;
  /** Translated role label shown under the name in the chip. */
  roleLabel: string;
  /** First grapheme of the user's name, for the avatar fallback. */
  avatarInitial: string;
  onLogout: () => void;
}

/**
 * Top-bar identity chip turned into an accessible dropdown menu. Clicking the
 * chip opens a popover with the user's name/email header, a link to their
 * role-specific profile page, and a logout action. Closes on outside-click,
 * Escape, or route change.
 */
export function TopBarUserMenu({
  userName,
  roleLabel,
  avatarInitial,
  onLogout,
}: TopBarUserMenuProps) {
  const t = useTranslations("common");
  const { user, role } = useAuth();
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const wrapRef = useRef<HTMLDivElement>(null);
  const lastPathRef = useRef(pathname);

  // Prefer the live auth context (carries email + freshest avatar) but fall
  // back to the server-provided name so the chip never renders empty pre-hydrate.
  const displayName = user?.name ?? userName;
  const email = user?.email ?? "";
  const avatarSrc = user?.avatar ?? null;
  const profileRole: UserRole = role ?? user?.role ?? "freelancer";

  // dashboardFor("/owner") → "/owner", etc. The profile route is "<base>/profile".
  const profileHref = `${dashboardFor(profileRole)}/profile`;

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

  // Close on navigation so the menu never lingers over a new page. Guarded by a
  // ref so state is only touched when the path actually changes (mirrors the
  // DashShell drawer-close pattern), satisfying the no-setState-in-effect rule.
  useEffect(() => {
    if (lastPathRef.current !== pathname) {
      lastPathRef.current = pathname;
      setOpen(false);
    }
  }, [pathname]);

  return (
    <div className="topbar-user" ref={wrapRef}>
      <button
        type="button"
        className="tb-user-chip"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={t("account")}
        onClick={() => setOpen((o) => !o)}
      >
        <Avatar initial={avatarInitial} src={avatarSrc} round />
        <span className="tb-user-meta">
          <span className="tb-user-name">{displayName}</span>
          <span className="tb-user-role">{roleLabel}</span>
        </span>
        <Icon name="chevD" size={16} className="tb-user-caret" />
      </button>

      {open && (
        <div className="tb-user-menu" role="menu">
          <div className="tb-user-menu-head">
            <Avatar initial={avatarInitial} src={avatarSrc} round size="sm" />
            <div className="tb-user-menu-id">
              <div className="tb-user-menu-name">{displayName}</div>
              {email && <div className="tb-user-menu-email ltr">{email}</div>}
            </div>
          </div>

          <div className="tb-user-menu-sep" />

          <Link
            href={profileHref}
            className="tb-user-menu-item"
            role="menuitem"
            onClick={() => setOpen(false)}
          >
            <Icon name="user" size={17} />
            <span>{t("profile")}</span>
          </Link>

          <button
            type="button"
            className="tb-user-menu-item danger"
            role="menuitem"
            onClick={() => {
              setOpen(false);
              onLogout();
            }}
          >
            <Icon name="logout" size={17} />
            <span>{t("logout")}</span>
          </button>
        </div>
      )}
    </div>
  );
}
