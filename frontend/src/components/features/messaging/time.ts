/**
 * Locale-aware relative time ("2h ago" / "قبل ساعتين"). Uses the platform
 * `Intl.RelativeTimeFormat`, so plural/grammatical forms are handled per-locale.
 * Pure + deterministic given a `now`, which keeps it safe for client rendering.
 */
export function relativeTime(
  iso: string | null | undefined,
  locale: string,
  now: number = Date.now(),
): string {
  if (!iso) return "";
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return "";

  const diffSec = Math.round((then - now) / 1000);
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

/** Short clock time (HH:MM) for message bubbles, locale-formatted. */
export function clockTime(
  iso: string | null | undefined,
  locale: string,
): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return new Intl.DateTimeFormat(locale, {
    hour: "2-digit",
    minute: "2-digit",
  }).format(d);
}
