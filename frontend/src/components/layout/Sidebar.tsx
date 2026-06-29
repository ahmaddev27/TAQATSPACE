"use client";

import { useTranslations } from "next-intl";
import { Link, usePathname } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { BrandLogo } from "./BrandLogo";
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

/** Does `href` match the current path? Index routes match exactly; others by prefix. */
function matchesPath(pathname: string, href: string, indexHref: string): boolean {
  if (href === indexHref) return pathname === href;
  return pathname === href || pathname.startsWith(`${href}/`);
}

/**
 * The single active nav href = the MOST SPECIFIC (longest) matching item, so a
 * parent like `/admin/settings/messaging` is not also highlighted when a child
 * like `/admin/settings/messaging/broadcast` is open.
 */
function findActiveHref(
  pathname: string,
  hrefs: string[],
  indexHref: string,
): string | null {
  let best: string | null = null;
  for (const href of hrefs) {
    if (
      matchesPath(pathname, href, indexHref) &&
      (best === null || href.length > best.length)
    ) {
      best = href;
    }
  }
  return best;
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
  const activeHref = findActiveHref(
    pathname,
    nav.groups.flatMap((g) => g.items.map((i) => i.href)),
    indexHref,
  );

  // The desktop rail can be collapsed to icons, but the mobile drawer always
  // shows full labels — so labels render whenever the drawer is open, even if
  // `collapsed` was toggled on a wider viewport before resizing down.
  const showLabels = !collapsed || mobileOpen;

  return (
    <aside
      id="dash-sidebar"
      className={`dash-nav ${collapsed ? "is-collapsed" : ""} ${
        mobileOpen ? "mobile-open" : ""
      }`.trim()}
      aria-label={tc("navigation")}
    >
      <div className="dash-brand">
        <Link href={indexHref} className="logo" onClick={onNavigate}>
          {!showLabels ? (
            <span className="tile-glyph" style={{ fontSize: 24 }}>
              T
            </span>
          ) : (
            <BrandLogo size={26} />
          )}
        </Link>
      </div>

      <div className="dash-nav-scroll">
        {nav.groups.map((group, gi) => (
          <div key={gi}>
            {group.titleKey && showLabels && (
              <div className="nav-section">{t(group.titleKey)}</div>
            )}
            {group.titleKey && !showLabels && <div className="nav-sep" />}
            {group.items.map((item) => {
              const active = item.href === activeHref;
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
                  {showLabels && <span>{label}</span>}
                  {showLabels && item.badge ? (
                    <span
                      className={`nav-badge tnum ${
                        item.badgeTone === "action" ? "nav-badge--action" : ""
                      }`.trim()}
                    >
                      {item.badge}
                    </span>
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
          {showLabels && <span>{tc("logout")}</span>}
        </button>
      </div>
    </aside>
  );
}
