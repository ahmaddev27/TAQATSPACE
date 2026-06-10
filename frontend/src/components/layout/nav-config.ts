import type { IconName } from "@/components/ui/Icon";
import type { AdminPermission, UserRole } from "@/lib/types/auth";

export interface NavItem {
  /** Translation key under `nav.items.*`. */
  key: string;
  /** Route relative to the locale root (e.g. `/owner/members`). */
  href: string;
  icon: IconName;
  /** Optional notification count badge. */
  badge?: number;
  /**
   * When set, the item is shown only to users whose permission grant includes
   * this permission (admin accounts carry `permissions` on the auth payload).
   * Items without it are visible to everyone in the role.
   */
  permission?: AdminPermission;
  /**
   * Gates the item on realtime-chat (Firebase) availability so the in-app 1:1
   * surface is never duplicated. `"primary"` items (the realtime Chat) show only
   * when Firebase is configured; `"fallback"` items (the legacy DB Messages) show
   * only when it is not. Items without this field are unaffected.
   */
  realtime?: "primary" | "fallback";
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
 * Drop permission-gated items the current user may not access, then prune any
 * group left empty. Items without a `permission` are always kept. `permissions`
 * is the admin account's effective grant (absent for non-admin roles, which
 * have no gated items anyway).
 */
export function filterNavByPermissions(
  nav: RoleNav,
  permissions: readonly AdminPermission[] | undefined,
  isSuperAdmin = false,
): RoleNav {
  // A super-admin holds every permission — never hide anything from them, even
  // if the permission list arrives empty for any reason.
  if (isSuperAdmin) return nav;

  const granted = new Set(permissions ?? []);

  const groups = nav.groups
    .map((group) => ({
      ...group,
      items: group.items.filter(
        (item) => !item.permission || granted.has(item.permission),
      ),
    }))
    .filter((group) => group.items.length > 0);

  return { ...nav, groups };
}

/**
 * Resolve the realtime-chat-vs-legacy-messages duplication: when Firebase is
 * configured the realtime Chat (`realtime: "primary"`) is the single 1:1 surface
 * and the DB Messages fallback (`realtime: "fallback"`) is hidden; when it is
 * not, the reverse. Items without a `realtime` field are always kept. Empty
 * groups are pruned. Keeps the user from ever seeing two 1:1 tools — or none.
 */
export function filterNavByRealtime(
  nav: RoleNav,
  firebaseReady: boolean,
): RoleNav {
  const groups = nav.groups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => {
        if (item.realtime === "primary") return firebaseReady;
        if (item.realtime === "fallback") return !firebaseReady;
        return true;
      }),
    }))
    .filter((group) => group.items.length > 0);

  return { ...nav, groups };
}

/**
 * Set the unread-messages badge on the Chat nav item (across every group). A
 * count of 0 leaves the nav untouched, so no badge renders. Returns a new nav
 * object — the input is never mutated.
 */
export function applyChatBadge(nav: RoleNav, unread: number): RoleNav {
  if (unread <= 0) return nav;

  return {
    ...nav,
    groups: nav.groups.map((group) => ({
      ...group,
      items: group.items.map((item) =>
        item.key === "chat" ? { ...item, badge: unread } : item,
      ),
    })),
  };
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
        titleKey: "sectionManagement",
        items: [
          { key: "expenses", href: "/owner/expenses", icon: "wallet" },
          { key: "resources", href: "/owner/resources", icon: "layers" },
        ],
      },
      {
        titleKey: "sectionEngage",
        items: [
          { key: "chat", href: "/owner/chat", icon: "chat", realtime: "primary" },
          { key: "messages", href: "/owner/messages", icon: "chat", realtime: "fallback" },
          { key: "broadcast", href: "/owner/messaging", icon: "send" },
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
          { key: "explore", href: "/explore", icon: "search" },
          { key: "chat", href: "/freelancer/chat", icon: "chat", realtime: "primary" },
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
          {
            key: "workspaces",
            href: "/admin/workspaces",
            icon: "building",
            permission: "manage_workspaces",
          },
          {
            key: "users",
            href: "/admin/users",
            icon: "users",
            permission: "manage_users",
          },
          {
            key: "admins",
            href: "/admin/admins",
            icon: "shield",
            permission: "manage_admins",
          },
          {
            key: "subscriptions",
            href: "/admin/subscriptions",
            icon: "card",
            permission: "manage_billing",
          },
          {
            key: "invoices",
            href: "/admin/invoices",
            icon: "receipt",
            permission: "manage_billing",
          },
          {
            key: "reports",
            href: "/admin/reports",
            icon: "chart",
            permission: "view_reports",
          },
        ],
      },
      {
        titleKey: "sectionCrm",
        items: [
          {
            key: "landing",
            href: "/admin/landing",
            icon: "grid",
            permission: "manage_content",
          },
          {
            key: "crm",
            href: "/admin/crm",
            icon: "settings",
            permission: "manage_content",
          },
        ],
      },
      {
        titleKey: "sectionSettings",
        items: [
          {
            key: "chat",
            href: "/admin/chat",
            icon: "chat",
            realtime: "primary",
          },
          {
            key: "messaging",
            href: "/admin/settings/messaging",
            icon: "chat",
            permission: "manage_messaging",
          },
          {
            key: "broadcast",
            href: "/admin/settings/messaging/broadcast",
            icon: "send",
            permission: "manage_messaging",
          },
          {
            key: "inbox",
            href: "/admin/messages",
            icon: "mail",
            permission: "manage_messaging",
          },
        ],
      },
    ],
  },
};
