import { describe, expect, it } from "vitest";
import {
  ROLE_NAV,
  filterNavByFeatures,
  filterNavByPermissions,
  filterNavByRealtime,
  type RoleNav,
} from "./nav-config";

const SRS_GROUPS = [
  "sectionHome",
  "sectionCommercial",
  "sectionFinance",
  "sectionGsg",
  "sectionAnalytics",
  "sectionPlaces",
  "sectionSettings",
];

const hrefs = (nav: RoleNav) =>
  nav.groups.flatMap((g) =>
    g.items.flatMap((i) => (i.children ? i.children.map((c) => c.href) : [i.href])),
  );

describe("owner nav structure", () => {
  const owner = ROLE_NAV.workspace_owner;

  it("lists the seven SRS groups in order", () => {
    expect(owner.groups.map((g) => g.titleKey)).toEqual(SRS_GROUPS);
  });

  it("starts with the dashboard so the brand link resolves to /owner", () => {
    expect(owner.groups[0].items[0].href).toBe("/owner");
  });

  it("keeps every pre-existing owner route reachable", () => {
    const all = hrefs(owner);
    for (const route of [
      "/owner/members",
      "/owner/subscriptions",
      "/owner/seats",
      "/owner/requests",
      "/owner/packages",
      "/owner/resources",
      "/owner/pos/terminal",
      "/owner/pos",
      "/owner/pos/reports",
      "/owner/settings/staff",
      "/owner/invoices",
      "/owner/expenses",
      "/owner/chat",
      "/owner/messages",
      "/owner/messaging",
      "/owner/announcements",
      "/owner/reports",
      "/owner/settings",
      "/owner/settings/audit",
    ]) {
      expect(all).toContain(route);
    }
  });
});

describe("owner nav filtering", () => {
  const apply = (features: Parameters<typeof filterNavByFeatures>[1]) =>
    filterNavByRealtime(
      filterNavByFeatures(
        filterNavByPermissions(ROLE_NAV.workspace_owner, undefined),
        features,
      ),
      true,
    );

  it("removes the GSG group entirely while its flag is off", () => {
    const nav = apply({ gsg: false });

    expect(nav.groups.map((g) => g.titleKey)).not.toContain("sectionGsg");
    expect(hrefs(nav)).not.toContain("/owner/gsg");
  });

  it("shows the GSG group once the flag is on", () => {
    const nav = apply({ gsg: true });

    expect(nav.groups.map((g) => g.titleKey)).toContain("sectionGsg");
  });

  it("hides the network item without its flag", () => {
    expect(hrefs(apply({}))).not.toContain("/owner/network");
    expect(hrefs(apply({ network: true }))).toContain("/owner/network");
  });

  it("falls back to legacy messages when realtime is unavailable", () => {
    const nav = filterNavByRealtime(ROLE_NAV.workspace_owner, false);

    expect(hrefs(nav)).toContain("/owner/messages");
    expect(hrefs(nav)).not.toContain("/owner/chat");
  });
});
