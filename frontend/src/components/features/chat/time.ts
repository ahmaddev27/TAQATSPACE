/**
 * Time helpers for the realtime chat, operating on epoch millis (Firestore
 * timestamps are surfaced as millis). Locale-aware via `Intl`. A null value
 * (e.g. a serverTimestamp still resolving) yields an empty string.
 */

/** Short clock time (HH:MM) for message bubbles. */
export function clockTime(millis: number | null, locale: string): string {
  if (millis == null) return "";
  return new Intl.DateTimeFormat(locale, {
    hour: "2-digit",
    minute: "2-digit",
  }).format(millis);
}

/** Locale-aware relative time ("2h ago" / "قبل ساعتين") for list previews. */
export function relativeTime(
  millis: number | null,
  locale: string,
  now: number = Date.now(),
): string {
  if (millis == null) return "";

  const diffSec = Math.round((millis - now) / 1000);
  const abs = Math.abs(diffSec);
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: "auto" });

  if (abs < 60) return rtf.format(Math.round(diffSec), "second");
  const diffMin = Math.round(diffSec / 60);
  if (Math.abs(diffMin) < 60) return rtf.format(diffMin, "minute");
  const diffHr = Math.round(diffMin / 60);
  if (Math.abs(diffHr) < 24) return rtf.format(diffHr, "hour");
  const diffDay = Math.round(diffHr / 24);
  if (Math.abs(diffDay) < 7) return rtf.format(diffDay, "day");
  const diffWk = Math.round(diffDay / 7);
  if (Math.abs(diffWk) < 5) return rtf.format(diffWk, "week");
  const diffMo = Math.round(diffDay / 30);
  if (Math.abs(diffMo) < 12) return rtf.format(diffMo, "month");
  return rtf.format(Math.round(diffDay / 365), "year");
}
