"use client";

import { useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { Select } from "@/components/ui/Select";
import type { Branch, Seat } from "@/lib/types";

export interface SeatInventoryPanelProps {
  seats: Seat[];
  branches: Branch[];
}

/** "Inventory" tab: every station with its zone and branch, filterable. */
export function SeatInventoryPanel({ seats, branches }: SeatInventoryPanelProps) {
  const t = useTranslations("owner");
  const [branchId, setBranchId] = useState("");
  const [zone, setZone] = useState("");

  const zones = useMemo(
    () => [...new Set(seats.map((s) => s.zone).filter((z): z is string => !!z))].sort(),
    [seats],
  );
  const branchName = useMemo(
    () => new Map(branches.map((b) => [b.id, b.name])),
    [branches],
  );
  const visible = seats.filter(
    (s) => (!branchId || s.branch_id === branchId) && (!zone || s.zone === zone),
  );

  return (
    <div className="card card-pad stack" style={{ gap: 14 }}>
      <div className="row" style={{ gap: 12 }}>
        <Select
          aria-label={t("seats.stations.branch")}
          value={branchId}
          onChange={(e) => setBranchId(e.target.value)}
        >
          <option value="">{t("seats.stations.allBranches")}</option>
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {b.name}
            </option>
          ))}
        </Select>
        <Select
          aria-label={t("seats.stations.zone")}
          value={zone}
          onChange={(e) => setZone(e.target.value)}
        >
          <option value="">{t("seats.stations.allZones")}</option>
          {zones.map((z) => (
            <option key={z} value={z}>
              {z}
            </option>
          ))}
        </Select>
      </div>
      {visible.length === 0 ? (
        <p className="muted">{t("seats.stations.noMatch")}</p>
      ) : (
        <table className="table">
          <thead>
            <tr>
              <th>{t("seats.stations.colNumber")}</th>
              <th>{t("seats.stations.colType")}</th>
              <th>{t("seats.stations.colKind")}</th>
              <th>{t("seats.stations.colZone")}</th>
              <th>{t("seats.stations.colBranch")}</th>
              <th>{t("seats.stations.colStatus")}</th>
            </tr>
          </thead>
          <tbody>
            {visible.map((seat) => (
              <tr key={seat.id}>
                <td className="tnum">{seat.seat_number}</td>
                <td>{t(`settings.seatTypeNames.${seat.type}`)}</td>
                <td>{t(`seats.stations.kindNames.${seat.kind ?? "desk"}`)}</td>
                <td>{seat.zone ?? "-"}</td>
                <td>{(seat.branch_id && branchName.get(seat.branch_id)) || "-"}</td>
                <td>{t(`seats.${seat.status}`)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
