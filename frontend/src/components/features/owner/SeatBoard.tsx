"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { SeatLegend, type SeatLegendItem } from "@/components/ui/SeatLegend";
import { SeatMap, type SeatMapState } from "@/components/ui/SeatMap";
import { useToast } from "@/components/providers/ToastProvider";
import { assignSeat, unassignSeat } from "@/lib/actions/owner";
import type { Member, Seat, SeatType } from "@/lib/types";
import { avatarInitial } from "./format";
import { AssignSeatModal } from "./AssignSeatModal";

export interface SeatBoardProps {
  seats: Seat[];
  members: Member[];
}

/** Stable display order for the seat-type groups. */
const SEAT_TYPE_ORDER: SeatType[] = ["flexible", "fixed", "private_office"];

interface SeatTypeGroup {
  type: SeatType;
  seats: Seat[];
  total: number;
  occupied: number;
  available: number;
}

function toState(status: Seat["status"]): SeatMapState {
  if (status === "occupied") return "occupied";
  if (status === "reserved") return "reserved";
  if (status === "maintenance") return "disabled";
  return "available";
}

/** Group seats by their type, preserving a stable type order. */
function groupByType(seats: Seat[]): SeatTypeGroup[] {
  const buckets = new Map<SeatType, Seat[]>();
  for (const seat of seats) {
    const bucket = buckets.get(seat.type);
    if (bucket) bucket.push(seat);
    else buckets.set(seat.type, [seat]);
  }

  const orderedTypes = [
    ...SEAT_TYPE_ORDER.filter((type) => buckets.has(type)),
    ...[...buckets.keys()].filter((type) => !SEAT_TYPE_ORDER.includes(type)),
  ];

  return orderedTypes.map((type) => {
    const group = buckets.get(type) ?? [];
    return {
      type,
      seats: group,
      total: group.length,
      occupied: group.filter((s) => s.status === "occupied").length,
      available: group.filter((s) => s.status === "available").length,
    };
  });
}

/** Interactive seat map: assign available seats, unassign occupied ones. */
export function SeatBoard({ seats, members }: SeatBoardProps) {
  const t = useTranslations("owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [selectedId, setSelectedId] = useState<string | null>(null);

  const typeGroups = useMemo(() => groupByType(seats), [seats]);

  const typeName = (type: SeatType): string =>
    t(`settings.seatTypeNames.${type}`);

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

        <div className="stack" style={{ gap: 22 }}>
          {typeGroups.map((group) => (
            <div key={group.type} className="stack" style={{ gap: 12 }}>
              <div className="between">
                <span className="floor-zone" style={{ margin: 0 }}>
                  {typeName(group.type)}
                </span>
                <span className="tnum muted" style={{ fontSize: "var(--fs-sm)" }}>
                  {group.available} {t("seats.free")} / {group.total}
                </span>
              </div>
              <SeatMap
                seats={group.seats.map((s) => ({
                  id: s.id,
                  label: s.seat_number,
                  state: toState(s.status),
                  member: s.assigned_member
                    ? {
                        name: s.assigned_member.name,
                        initial: avatarInitial(s.assigned_member.name),
                        avatarUrl: s.assigned_member.avatar_url,
                      }
                    : null,
                }))}
                selected={selectedId}
                onSelect={setSelectedId}
                cols={8}
              />
            </div>
          ))}
        </div>

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
                    src={selectedSeat.assigned_member.avatar_url}
                    alt={selectedSeat.assigned_member.name}
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

          {typeGroups.length > 1 && (
            <>
              <div className="divider" style={{ margin: "4px 0" }} />
              <div className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
                {t("seats.byType")}
              </div>
              <div className="stack" style={{ gap: 8 }}>
                {typeGroups.map((group) => (
                  <SummaryRow
                    key={group.type}
                    label={typeName(group.type)}
                    value={`${group.occupied}/${group.total}`}
                  />
                ))}
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

function SummaryRow({
  label,
  value,
}: {
  label: string;
  value: number | string;
}) {
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
