/** Shared, locale-aware formatting helpers for the owner dashboard. */

import { formatDate } from "@/lib/dates";

/** First grapheme of a name (Arabic + Latin safe), uppercased. */
export function avatarInitial(name: string | null | undefined): string {
  const first = Array.from((name ?? "").trim())[0] ?? "?";
  return first.toUpperCase();
}

/** Format an ISO date as a short, LTR numeric date. Empty string for null. */
export function shortDate(iso: string | null | undefined): string {
  if (!iso) return "—";
  if (Number.isNaN(new Date(iso).getTime())) return "—";
  return formatDate(iso);
}

/** Format a numeric/string amount with the shekel sign, no decimals when whole. */
export function money(value: number | string | null | undefined): string {
  const n = typeof value === "string" ? Number(value) : (value ?? 0);
  if (Number.isNaN(n)) return "₪0";
  const rounded = Math.round(n * 100) / 100;
  const text = Number.isInteger(rounded)
    ? String(rounded)
    : rounded.toFixed(2);
  return `₪${text}`;
}
