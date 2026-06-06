"use client";

import { useState, type FormEvent } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { Icon } from "@/components/ui/Icon";
import { Avatar } from "@/components/ui/Avatar";
import type { UserRole } from "@/lib/types/auth";
import { LanguageToggle } from "./LanguageToggle";
import { ThemeToggle } from "./ThemeToggle";
import { NotificationBell } from "./NotificationBell";

export interface TopNavProps {
  role: UserRole;
  userName: string;
  roleLabel: string;
  avatarInitial: string;
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
        className="icon-btn"
        onClick={onMenu}
        aria-label="menu"
      >
        <Icon name="menu" />
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

      <div className="topbar-user">
        <Avatar initial={avatarInitial} round />
        <div className="tb-user-meta">
          <div className="tb-user-name">{userName}</div>
          <div className="tb-user-role">{roleLabel}</div>
        </div>
        <button
          type="button"
          className="icon-btn"
          onClick={onLogout}
          aria-label={t("logout")}
          title={t("logout")}
        >
          <Icon name="logout" />
        </button>
      </div>
    </div>
  );
}
