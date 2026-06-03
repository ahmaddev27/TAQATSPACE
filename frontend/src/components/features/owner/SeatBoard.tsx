"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { SeatLegend, type SeatLegendItem } from "@/components/ui/SeatLegend";
import { SeatMap, type SeatMapSeat, type SeatMapState } from "@/components/ui/SeatMap";
import { useToast } from "@/components/providers/ToastProvider";
import { assignSeat, unassignSeat } from "@/lib/actions/owner";
import type { Member, Seat } from "@/lib/types";
import { avatarInitial } from "./format";
import { AssignSeatModal } from "./AssignSeatModal";

export interface SeatBoardProps {
  seats: Seat[];
  members: Member[];
}

function toState(status: Seat["status"]): SeatMapState {
  if (status === "occupied") return "occupied";
  if (status === "reserved") return "reserved";
  if (status === "maintenance") return "disabled";
  return "available";
}

/** Interactive seat map: assign available seats, unassign occupied ones. */
export function SeatBoard({ seats, members }: SeatBoardProps) {
  const t = useTranslations("owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [selectedId, setSelectedId] = useState<string | null>(null);

  const mapSeats: SeatMapSeat[] = useMemo(
    () =>
      seats.map((s) => ({
        id: s.id,
        label: s.seat_number,
        state: toState(s.status),
      })),
    [seats],
  );

  const stats = useMemo(() => {
    const available = seats.filter((s) => s.status === "available").length;
    const occupied = seats.filter((s) => s.status === "occupied").length;
    const reserved = seats.filter((s) => s.status === "reserved").length;
    return { available, occupied, reserved, total: seats.length };
  }, [seats]);

  const selectedSeat = seats.find((s) => s.id === selectedId) ?? null;
  const isOccupiedSelected = selectedSeat?.status === "occupied";

  const activeMembers = useMemo(
    () => members.filter((m) => m.status === "active"),
    [members],
  );

  const legendItems: SeatLegendItem[] = [
    { label: t("seats.available"), bg: "--seat-available", bd: "--seat-available-bd" },
    { label: t("seats.selectedSeat"), bg: "--seat-selected", bd: "--seat-selected-bd" },
    { label: t("seats.occupied"), bg: "--seat-occupied", bd: "--seat-occupied-bd" },
    { label: t("seats.reserved"), bg: "--seat-reserved", bd: "--seat-reserved-bd" },
    { label: t("seats.maintenance"), bg: "--seat-disabled", bd: "--seat-disabled-bd" },
  ];

  const handleAssign = (memberId: string) => {
    if (!selectedSeat) return;
    startTransition(async () => {
      const res = await assignSeat(selectedSeat.id, memberId);
      if (res.ok) {
        toast({ tone: "ok", title: t("seats.assigned"), body: selectedSeat.seat_number });
        setSelectedId(null);
      } else {
        toast({ tone: "err", title: t("seats.assignFailed"), body: res.message });
      }
    });
  };

  const handleUnassign = () => {
    if (!selectedSeat) return;
    startTransition(async () => {
      const res = await unassignSeat(selectedSeat.id);
      if (res.ok) {
        toast({ tone: "ok", title: t("seats.unassigned"), body: selectedSeat.seat_number });
        setSelectedId(null);
      } else {
        toast({ tone: "err", title: t("seats.unassignFailed"), body: res.message });
      }
    });
  };

  return (
    <div className="seat-page">
      <div className="seat-floor">
        <div className="between" style={{ marginBottom: 18 }}>
          <div className="floor-zone" style={{ margin: 0 }}>
            {t("seats.zoneOpen")}
          </div>
          <div className="row" style={{ gap: 8 }}>
            <span className="badge badge-success">
              <span className="dot" />
              {stats.available} {t("seats.free")}
            </span>
            <span className="badge badge-neutral">
              {stats.occupied} {t("seats.taken")}
            </span>
          </div>
        </div>

        <SeatMap
          seats={mapSeats}
          selected={selectedId}
          onSelect={setSelectedId}
          cols={8}
        />

        <div className="divider" style={{ margin: "22px 0 16px" }} />
        <SeatLegend items={legendItems} />
      </div>

      <div className="stack" style={{ gap: 16 }}>
        <div className="card card-pad stack" style={{ gap: 14 }}>
          <h3 className="h3">
            {isOccupiedSelected
              ? t("seats.occupiedTitle", { seat: selectedSeat?.seat_number ?? "" })
              : t("seats.assignTitle")}
          </h3>

          {!selectedSeat ? (
            <div className="empty-state" style={{ padding: "28px 12px" }}>
              <div className="st-ico amber" style={{ width: 44, height: 44 }}>
                <Icon name="grid" />
              </div>
              <div style={{ fontSize: "var(--fs-sm)" }}>
                {t("seats.assignPrompt")}
              </div>
            </div>
          ) : isOccupiedSelected ? (
            <div className="stack" style={{ gap: 14 }}>
              <div className="between">
                <span className="muted">{t("seats.selectedSeat")}</span>
                <span className="badge badge-info tnum">
                  {selectedSeat.seat_number}
                </span>
              </div>
              {selectedSeat.assigned_member && (
                <div className="row" style={{ gap: 10 }}>
                  <Avatar
                    initial={avatarInitial(selectedSeat.assigned_member.name)}
                    size="sm"
                    round
                  />
                  <div className="grow">
                    <div className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
                      {t("seats.assignedTo")}
                    </div>
                    <div style={{ fontSize: "var(--fs-sm)", fontWeight: 500 }}>
                      {selectedSeat.assigned_member.name}
                    </div>
                  </div>
                </div>
              )}
              <Button
                variant="danger"
                block
                icon="x"
                loading={pending}
                onClick={handleUnassign}
              >
                {t("seats.unassign")}
              </Button>
              <Button variant="ghost" block onClick={() => setSelectedId(null)}>
                {t("seats.cancel")}
              </Button>
            </div>
          ) : (
            <AssignSeatModal
              seatLabel={selectedSeat.seat_number}
              members={activeMembers}
              pending={pending}
              onAssign={handleAssign}
              onCancel={() => setSelectedId(null)}
            />
          )}
        </div>

        <div className="card card-pad stack" style={{ gap: 12 }}>
          <div className="between">
            <h3 className="h3">{t("seats.summary")}</h3>
            <span className="tnum muted">
              {stats.occupied}/{stats.total}
            </span>
          </div>
          <div className="bar-track">
            <div
              className="bar-fill"
              style={{
                width: `${stats.total ? (stats.occupied / stats.total) * 100 : 0}%`,
              }}
            />
          </div>
          <div className="stack" style={{ gap: 8, marginTop: 4 }}>
            <SummaryRow label={t("seats.available")} value={stats.available} />
            <SummaryRow label={t("seats.occupied")} value={stats.occupied} />
            <SummaryRow label={t("seats.reserved")} value={stats.reserved} />
            <SummaryRow label={t("seats.totalSeats")} value={stats.total} />
          </div>
        </div>
      </div>
    </div>
  );
}

function SummaryRow({ label, value }: { label: string; value: number }) {
  return (
    <div className="between">
      <span className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {label}
      </span>
      <span className="tnum" style={{ fontWeight: 600 }}>
        {value}
      </span>
    </div>
  );
}
