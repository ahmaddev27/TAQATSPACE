"use client";

import { Icon } from "./Icon";

export type SeatMapState =
  | "available"
  | "occupied"
  | "reserved"
  | "disabled";

export interface SeatMapSeat {
  id: string;
  label: string;
  state: SeatMapState;
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
      style={{ gridTemplateColumns: `repeat(${cols}, 1fr)` }}
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
        const locked = s.state === "disabled" || s.state === "occupied";
        return (
          <button
            key={s.id}
            type="button"
            className={`seat ${cls}`.trim()}
            disabled={locked}
            onClick={() => onSelect?.(s.id)}
            title={s.label}
          >
            {s.state === "occupied" ? (
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
