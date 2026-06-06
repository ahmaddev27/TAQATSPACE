import type { IconName } from "@/components/ui/Icon";
import type { UserRole } from "@/lib/types/auth";

export interface NavItem {
  /** Translation key under `nav.items.*`. */
  key: string;
  /** Route relative to the locale root (e.g. `/owner/members`). */
  href: string;
  icon: IconName;
  /** Optional notification count badge. */
  badge?: number;
}

export interface NavGroup {
  /** Translation key under `nav.*` for the section heading (optional). */
  titleKey?: string;
  items: NavItem[];
}

export interface RoleNav {
  /** Translation key for the role label shown in the topbar user menu. */
  roleLabelKey: string;
  groups: NavGroup[];
}

/**
 * Role-aware navigation, matching the prototype `NAV` map but pointing at real
 * Next routes. Screen agents own the destination pages; missing pages are
 * stubbed by their respective areas.
 */
export const ROLE_NAV: Record<UserRole, RoleNav> = {
  workspace_owner: {
    roleLabelKey: "ownerRole",
    groups: [
      {
        items: [{ key: "dashboard", href: "/owner", icon: "home" }],
      },
      {
        titleKey: "sectionManage",
        items: [
          { key: "members", href: "/owner/members", icon: "users" },
          { key: "subscriptions", href: "/owner/subscriptions", icon: "card" },
          { key: "seats", href: "/owner/seats", icon: "grid" },
          { key: "requests", href: "/owner/requests", icon: "inbox" },
          { key: "invoices", href: "/owner/invoices", icon: "receipt" },
          { key: "packages", href: "/owner/packages", icon: "wifi" },
        ],
      },
      {
        titleKey: "sectionEngage",
        items: [
          { key: "messages", href: "/owner/messages", icon: "chat" },
          { key: "announcements", href: "/owner/announcements", icon: "megaphone" },
          { key: "reports", href: "/owner/reports", icon: "chart" },
          { key: "settings", href: "/owner/settings", icon: "settings" },
        ],
      },
    ],
  },
  freelancer: {
    roleLabelKey: "freelancerRole",
    groups: [
      {
        items: [
          { key: "home", href: "/freelancer", icon: "home" },
          { key: "explore", href: "/freelancer/explore", icon: "search" },
        ],
      },
      {
        titleKey: "sectionAccount",
        items: [
          {
            key: "subscription",
            href: "/freelancer/subscription",
            icon: "card",
          },
          { key: "invoices", href: "/freelancer/invoices", icon: "receipt" },
          { key: "profile", href: "/freelancer/profile", icon: "user" },
        ],
      },
    ],
  },
  admin: {
    roleLabelKey: "adminRole",
    groups: [
      {
        items: [{ key: "analytics", href: "/admin", icon: "chart" }],
      },
      {
        titleKey: "sectionManagement",
        items: [
          { key: "workspaces", href: "/admin/workspaces", icon: "building" },
          { key: "users", href: "/admin/users", icon: "users" },
          { key: "subscriptions", href: "/admin/subscriptions", icon: "card" },
          { key: "invoices", href: "/admin/invoices", icon: "receipt" },
          { key: "reports", href: "/admin/reports", icon: "chart" },
        ],
      },
      {
        titleKey: "sectionCrm",
        items: [
          { key: "landing", href: "/admin/landing", icon: "grid" },
          { key: "crm", href: "/admin/crm", icon: "settings" },
        ],
      },
      {
        titleKey: "sectionSettings",
        items: [
          {
            key: "messaging",
            href: "/admin/settings/messaging",
            icon: "chat",
          },
        ],
      },
    ],
  },
};
