"use client";

import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { Avatar } from "@/components/ui/Avatar";
import { LanguageToggle } from "./LanguageToggle";
import { ThemeToggle } from "./ThemeToggle";

export interface TopNavProps {
  userName: string;
  roleLabel: string;
  avatarInitial: string;
  onMenu: () => void;
  onLogout: () => void;
}

/** Dashboard top bar: menu toggle, search, locale/theme, bell, user menu. */
export function TopNav({
  userName,
  roleLabel,
  avatarInitial,
  onMenu,
  onLogout,
}: TopNavProps) {
  const t = useTranslations("common");

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

      <div className="search input-icon">
        <Icon name="search" />
        <input className="input" placeholder={t("search")} aria-label={t("search")} />
      </div>

      <div className="grow" />

      <LanguageToggle />
      <ThemeToggle />

      <button
        type="button"
        className="icon-btn"
        aria-label={t("notifications")}
      >
        <Icon name="bell" />
        <span className="ind" />
      </button>

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
