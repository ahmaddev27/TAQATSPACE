import type { AnnouncementType } from "@/lib/api/announcements";

/** Emoji glyph that fronts each announcement type in badges and the type picker. */
export const TYPE_EMOJI: Record<AnnouncementType, string> = {
  offer: "📢",
  info: "ℹ️",
  alert: "⚠️",
};

/**
 * Maps each type to a foundation `.badge-*` colour variant.
 *  - offer → success (green, promotional)
 *  - info  → info (blue, neutral notice)
 *  - alert → warning (amber, attention)
 */
export const TYPE_BADGE_VARIANT: Record<AnnouncementType, string> = {
  offer: "badge-success",
  info: "badge-info",
  alert: "badge-warning",
};

/**
 * Format an ISO timestamp as a short LTR date + time. Returns an em dash for
 * empty values so callers can render it inline without null checks.
 */
export function shortDateTime(iso: string | null | undefined): string {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "—";
  const y = d.getFullYear();
  const mo = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  const hh = String(d.getHours()).padStart(2, "0");
  const mm = String(d.getMinutes()).padStart(2, "0");
  return `${y}-${mo}-${day} ${hh}:${mm}`;
}

/**
 * Convert an ISO string into the `YYYY-MM-DDTHH:mm` shape required by a native
 * `datetime-local` input (which has no timezone). Empty string when absent.
 */
export function toDatetimeLocal(iso: string | null | undefined): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return (
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` +
    `T${pad(d.getHours())}:${pad(d.getMinutes())}`
  );
}

/**
 * Parse a `datetime-local` value (local wall time) back into a full ISO string
 * for the API. Returns `null` for empty input so it serialises as a cleared field.
 */
export function fromDatetimeLocal(value: string): string | null {
  if (!value) return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return null;
  return d.toISOString();
}
