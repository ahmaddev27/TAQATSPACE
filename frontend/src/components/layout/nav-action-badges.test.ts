import { describe, expect, it } from "vitest";
import { applyActionBadges, type RoleNav } from "./nav-config";

function makeNav(): RoleNav {
  return {
    roleLabelKey: "ownerRole",
    groups: [
      {
        items: [
          { key: "requests", href: "/owner/requests", icon: "inbox" },
          { key: "invoices", href: "/owner/invoices", icon: "receipt" },
          { key: "members", href: "/owner/members", icon: "users" },
        ],
      },
    ],
  };
}

describe("applyActionBadges", () => {
  it("stamps a red action badge with the count when > 0", () => {
    const nav = applyActionBadges(makeNav(), { bookings: 3, receipts: 2 });
    const items = nav.groups[0].items;

    expect(items[0]).toMatchObject({ badge: 3, badgeTone: "action" });
    expect(items[1]).toMatchObject({ badge: 2, badgeTone: "action" });
  });

  it("renders no badge when the count is 0 or absent", () => {
    const nav = applyActionBadges(makeNav(), { bookings: 0 });
    const items = nav.groups[0].items;

    expect(items[0].badge).toBeUndefined();
    expect(items[1].badge).toBeUndefined();
    expect(items[2].badge).toBeUndefined();
  });

  it("maps admin workspaces count onto the workspaces nav item", () => {
    const adminNav: RoleNav = {
      roleLabelKey: "adminRole",
      groups: [
        { items: [{ key: "workspaces", href: "/admin/workspaces", icon: "building" }] },
      ],
    };

    const nav = applyActionBadges(adminNav, { workspaces: 5 });
    expect(nav.groups[0].items[0]).toMatchObject({ badge: 5, badgeTone: "action" });
  });

  it("leaves an existing badge (e.g. chat unread) untouched", () => {
    const navWithChat: RoleNav = {
      roleLabelKey: "ownerRole",
      groups: [{ items: [{ key: "invoices", href: "/owner/invoices", icon: "receipt", badge: 9 }] }],
    };

    const nav = applyActionBadges(navWithChat, { receipts: 4 });
    expect(nav.groups[0].items[0].badge).toBe(9);
    expect(nav.groups[0].items[0].badgeTone).toBeUndefined();
  });

  it("does not mutate the input nav", () => {
    const original = makeNav();
    applyActionBadges(original, { bookings: 7 });
    expect(original.groups[0].items[0].badge).toBeUndefined();
  });
});
