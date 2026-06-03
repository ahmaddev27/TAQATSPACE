export interface SeatLegendItem {
  label: string;
  /** CSS var name for the swatch background, e.g. `--seat-available`. */
  bg: string;
  /** CSS var name for the swatch border, e.g. `--seat-available-bd`. */
  bd: string;
}

const DEFAULT_ITEMS: SeatLegendItem[] = [
  { label: "Available", bg: "--seat-available", bd: "--seat-available-bd" },
  { label: "Selected", bg: "--seat-selected", bd: "--seat-selected-bd" },
  { label: "Occupied", bg: "--seat-occupied", bd: "--seat-occupied-bd" },
  { label: "Reserved", bg: "--seat-reserved", bd: "--seat-reserved-bd" },
  { label: "Disabled", bg: "--seat-disabled", bd: "--seat-disabled-bd" },
];

export interface SeatLegendProps {
  /** Override labels for i18n; falls back to English defaults. */
  items?: SeatLegendItem[];
}

/** Swatch legend for the seat map states. Pass localized `items`. */
export function SeatLegend({ items = DEFAULT_ITEMS }: SeatLegendProps) {
  return (
    <div className="seat-legend">
      {items.map((it) => (
        <span key={it.label} className="lg">
          <span
            className="sw"
            style={{
              background: `var(${it.bg})`,
              borderColor: `var(${it.bd})`,
            }}
          />
          {it.label}
        </span>
      ))}
    </div>
  );
}
