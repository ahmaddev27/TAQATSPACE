"use client";

import { useTranslations } from "next-intl";
import { Link, usePathname } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { TileLogo } from "./TileLogo";
import type { RoleNav } from "./nav-config";

export interface SidebarProps {
  nav: RoleNav;
  /** Desktop collapsed (icon-only) state. */
  collapsed: boolean;
  /** Mobile off-canvas open state. */
  mobileOpen: boolean;
  /** Called after navigating on mobile so the drawer closes. */
  onNavigate: () => void;
  /** Logout handler (rendered in the footer). */
  onLogout: () => void;
}

/** Is `href` the active route? Index routes match exactly; others by prefix. */
function isActive(pathname: string, href: string, indexHref: string): boolean {
  if (href === indexHref) return pathname === href;
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function Sidebar({
  nav,
  collapsed,
  mobileOpen,
  onNavigate,
  onLogout,
}: SidebarProps) {
  const t = useTranslations("nav");
  const tc = useTranslations("common");
  const pathname = usePathname();
  const indexHref = nav.groups[0]?.items[0]?.href ?? "/";

  return (
    <aside
      className={`dash-nav ${collapsed ? "is-collapsed" : ""} ${
        mobileOpen ? "mobile-open" : ""
      }`.trim()}
    >
      <div className="dash-brand">
        <Link href={indexHref} className="logo" onClick={onNavigate}>
          {collapsed ? (
            <span className="tile-glyph" style={{ fontSize: 24 }}>
              T
            </span>
          ) : (
            <TileLogo size={26} />
          )}
        </Link>
      </div>

      <div className="dash-nav-scroll">
        {nav.groups.map((group, gi) => (
          <div key={gi}>
            {group.titleKey && !collapsed && (
              <div className="nav-section">{t(group.titleKey)}</div>
            )}
            {group.titleKey && collapsed && <div className="nav-sep" />}
            {group.items.map((item) => {
              const active = isActive(pathname, item.href, indexHref);
              const label = t(`items.${item.key}`);
              return (
                <Link
                  key={item.key}
                  href={item.href}
                  className={`nav-item ${active ? "active" : ""}`.trim()}
                  title={label}
                  onClick={onNavigate}
                >
                  <Icon name={item.icon} />
                  {!collapsed && <span>{label}</span>}
                  {!collapsed && item.badge ? (
                    <span className="nav-badge tnum">{item.badge}</span>
                  ) : null}
                </Link>
              );
            })}
          </div>
        ))}
      </div>

      <div className="dash-nav-foot">
        <button type="button" className="nav-item" onClick={onLogout}>
          <Icon name="logout" />
          {!collapsed && <span>{tc("logout")}</span>}
        </button>
      </div>
    </aside>
  );
}
