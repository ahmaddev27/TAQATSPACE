"use client";

import { useState, type FormEvent } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { Icon } from "@/components/ui/Icon";
import type { UserRole } from "@/lib/types/auth";
import { LanguageToggle } from "./LanguageToggle";
import { ThemeToggle } from "./ThemeToggle";
import { NotificationBell } from "./NotificationBell";
import { TopBarUserMenu } from "./TopBarUserMenu";

export interface TopNavProps {
  role: UserRole;
  userName: string;
  roleLabel: string;
  avatarInitial: string;
  /** Whether the mobile off-canvas drawer is open (drives the hamburger state). */
  mobileOpen: boolean;
  onMenu: () => void;
  onLogout: () => void;
}

/**
 * Where the top-nav search jumps for each role. The destination list pages read
 * `?search=` off the URL (via `useUrlFilters`), so navigating with the query
 * pre-filters the table. Roles without a searchable directory map to `null`,
 * which hides the input entirely.
 */
const SEARCH_DEST: Record<UserRole, string | null> = {
  workspace_owner: "/owner/members",
  admin: "/admin/users",
  freelancer: null,
};

/** Dashboard top bar: menu toggle, search, locale/theme, bell, user menu. */
export function TopNav({
  role,
  userName,
  roleLabel,
  avatarInitial,
  mobileOpen,
  onMenu,
  onLogout,
}: TopNavProps) {
  const t = useTranslations("common");
  const locale = useLocale();
  const router = useRouter();
  const [query, setQuery] = useState("");

  const searchPath = SEARCH_DEST[role];

  const onSearch = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!searchPath) return;

    const q = query.trim();
    const base = `/${locale}${searchPath}`;
    router.push(q ? `${base}?search=${encodeURIComponent(q)}` : base);
  };

  return (
    <div className="topbar">
      <button
        type="button"
        className="icon-btn nav-toggle"
        onClick={onMenu}
        aria-label={mobileOpen ? t("closeMenu") : t("openMenu")}
        aria-expanded={mobileOpen}
        aria-controls="dash-sidebar"
      >
        <Icon name={mobileOpen ? "x" : "menu"} />
      </button>

      {searchPath && (
        // A bare <form> submits on Enter natively — no submit button needed,
        // which keeps the existing single-icon search styling intact.
        <form className="search input-icon" role="search" onSubmit={onSearch}>
          <Icon name="search" />
          <input
            className="input"
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t("search")}
            aria-label={t("search")}
          />
        </form>
      )}

      <div className="grow" />

      <LanguageToggle />
      <ThemeToggle />

      <NotificationBell />

      <TopBarUserMenu
        userName={userName}
        roleLabel={roleLabel}
        avatarInitial={avatarInitial}
        onLogout={onLogout}
      />
    </div>
  );
}
