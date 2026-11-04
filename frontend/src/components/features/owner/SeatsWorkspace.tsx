"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Tabs } from "@/components/ui/Tabs";
import type { Branch, Member, Seat, SeatMonthOccupancy } from "@/lib/types";
import { BranchesPanel } from "./BranchesPanel";
import { BulkAddSeatsModal } from "./BulkAddSeatsModal";
import { SeatBoard } from "./SeatBoard";
import { SeatInventoryPanel } from "./SeatInventoryPanel";
import { SeatMonthPanel } from "./SeatMonthPanel";

type TabId = "inventory" | "now" | "month" | "branches";

export interface SeatsWorkspaceProps {
  seats: Seat[];
  members: Member[];
  branches: Branch[];
  occupancy: SeatMonthOccupancy;
}

/** Owner stations page: Inventory / Now (the seat board) / Month / Branches. */
export function SeatsWorkspace({ seats, members, branches, occupancy }: SeatsWorkspaceProps) {
  const t = useTranslations("owner");
  const [tab, setTab] = useState<TabId>("now");
  const [bulkOpen, setBulkOpen] = useState(false);

  return (
    <div className="stack" style={{ gap: 16 }}>
      <div className="between">
        <Tabs
          value={tab}
          onChange={(id) => setTab(id as TabId)}
          items={[
            { id: "inventory", label: t("seats.stations.tabInventory") },
            { id: "now", label: t("seats.stations.tabNow") },
            { id: "month", label: t("seats.stations.tabMonth") },
            { id: "branches", label: t("seats.stations.tabBranches") },
          ]}
        />
        <Button variant="secondary" icon="plus" onClick={() => setBulkOpen(true)}>
          {t("seats.stations.bulkOpen")}
        </Button>
      </div>

      {tab === "inventory" && <SeatInventoryPanel seats={seats} branches={branches} />}
      {tab === "now" && <SeatBoard seats={seats} members={members} />}
      {tab === "month" && <SeatMonthPanel occupancy={occupancy} />}
      {tab === "branches" && <BranchesPanel branches={branches} />}

      {bulkOpen && <BulkAddSeatsModal branches={branches} onClose={() => setBulkOpen(false)} />}
    </div>
  );
}
