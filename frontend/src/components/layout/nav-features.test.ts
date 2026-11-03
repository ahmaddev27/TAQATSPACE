import { describe, expect, it } from "vitest";
import { filterNavByFeatures, type RoleNav } from "./nav-config";

function makeNav(): RoleNav {
  return {
    roleLabelKey: "ownerRole",
    groups: [
      {
        items: [
          { key: "members", href: "/owner/members", icon: "users" },
          { key: "network", href: "/owner/network", icon: "wifi", feature: "network" },
        ],
      },
      {
        titleKey: "sectionFinance",
        items: [
          { key: "gsg", href: "/owner/gsg", icon: "grid", feature: "gsg" },
        ],
      },
      {
        items: [
          {
            key: "group",
            href: "#",
            icon: "layers",
            children: [
              { key: "fin", href: "/owner/fin", icon: "wallet", feature: "finance_v2" },
            ],
          },
        ],
      },
    ],
  };
}

const keys = (nav: RoleNav) => nav.groups.flatMap((g) => g.items.map((i) => i.key));

describe("filterNavByFeatures", () => {
  it("hides gated items and empties groups when no flags are provided", () => {
    const nav = filterNavByFeatures(makeNav(), undefined);

    expect(keys(nav)).toEqual(["members"]);
    expect(nav.groups).toHaveLength(1);
  });

  it("keeps only the items whose flag is enabled", () => {
    const nav = filterNavByFeatures(makeNav(), { network: true, gsg: false });

    expect(keys(nav)).toEqual(["members", "network"]);
  });

  it("filters nested children and keeps the parent while one child survives", () => {
    const nav = filterNavByFeatures(makeNav(), { finance_v2: true });

    expect(keys(nav)).toEqual(["members", "group"]);
    expect(nav.groups[1].items[0].children?.[0].key).toBe("fin");
  });

  it("leaves ungated items untouched", () => {
    const nav = filterNavByFeatures(makeNav(), { network: true, gsg: true, finance_v2: true });

    expect(keys(nav)).toEqual(["members", "network", "gsg", "group"]);
  });
});
