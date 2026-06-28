"use client";

import { Avatar } from "./Avatar";
import { Icon } from "./Icon";

export type SeatMapState =
  | "available"
  | "occupied"
  | "reserved"
  | "disabled";

/** Assignee shown on an occupied seat tile. */
export interface SeatMapMember {
  name: string;
  initial: string;
  avatarUrl?: string | null;
}

export interface SeatMapSeat {
  id: string;
  label: string;
  state: SeatMapState;
  /** Present only for occupied seats the viewer is allowed to see. */
  member?: SeatMapMember | null;
}

export interface SeatMapProps {
  seats: SeatMapSeat[];
  selected?: string | null;
  onSelect?: (id: string) => void;
  cols?: number;
}

/** Interactive seat grid. Occupied/disabled seats are not selectable. */
export function SeatMap({ seats, selected, onSelect, cols = 8 }: SeatMapProps) {
  return (
    <div
      className="seatmap"
      style={{
        gridTemplateColumns: `repeat(${cols}, minmax(0, 84px))`,
        justifyContent: "start",
      }}
    >
      {seats.map((s) => {
        const isSelected = selected === s.id;
        const cls =
          s.state === "disabled"
            ? "is-disabled"
            : s.state === "occupied"
              ? "is-occupied"
              : s.state === "reserved"
                ? "is-reserved"
                : isSelected
                  ? "is-selected"
                  : "";
        // Occupied seats stay selectable so the owner can open them and unassign;
        // only maintenance ("disabled") seats are non-interactive.
        const occupiedWithMember = s.state === "occupied" && s.member != null;
        return (
          <button
            key={s.id}
            type="button"
            className={`seat ${cls}`.trim()}
            disabled={s.state === "disabled"}
            onClick={() => onSelect?.(s.id)}
            title={s.member ? `${s.label} — ${s.member.name}` : s.label}
          >
            {occupiedWithMember ? (
              <span className="seat-occupant">
                <span className="seat-tag tnum">{s.label}</span>
                <Avatar
                  initial={s.member!.initial}
                  src={s.member!.avatarUrl}
                  alt={s.member!.name}
                  size="sm"
                  round
                />
                <span className="seat-occupant-name">{s.member!.name}</span>
              </span>
            ) : s.state === "occupied" ? (
              <Icon name="user" size={15} />
            ) : s.state === "disabled" ? (
              <Icon name="x" size={14} />
            ) : (
              <span className="tnum">{s.label}</span>
            )}
          </button>
        );
      })}
    </div>
  );
}
