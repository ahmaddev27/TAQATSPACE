import type { Workspace } from "@/lib/types";
import type { PublicDict } from "./i18n";

/** Week order used across the app (Gaza working week starts Saturday). */
const DAY_KEYS = [
  "saturday",
  "sunday",
  "monday",
  "tuesday",
  "wednesday",
  "thursday",
  "friday",
] as const;

interface DayHours {
  open?: boolean;
  from?: string;
  to?: string;
}

export interface WorkingHoursProps {
  hours: Workspace["working_hours"];
  dict: PublicDict;
}

/**
 * Read-only weekly working-hours table for the public workspace page. Renders
 * nothing when the owner never configured hours, so the section never shows an
 * empty shell.
 */
export function WorkingHours({ hours, dict }: WorkingHoursProps) {
  const d = dict.detail;
  const source = (hours ?? {}) as Record<string, DayHours>;
  const hasAny = DAY_KEYS.some((day) => source[day]);

  if (!hasAny) return null;

  return (
    <div>
      <h3 className="h3" style={{ marginBottom: 14 }}>
        {d.workingHours}
      </h3>
      <div className="hours-table">
        {DAY_KEYS.map((day) => {
          const entry = source[day];
          const isOpen = Boolean(entry?.open && entry?.from && entry?.to);
          return (
            <div key={day} className="hours-line">
              <span className="hours-line-day">{d.days[day]}</span>
              {isOpen ? (
                <span className="hours-line-time tnum ltr">
                  {entry!.from} – {entry!.to}
                </span>
              ) : (
                <span className="hours-line-closed">{d.closed}</span>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
