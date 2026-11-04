/** The platform's default workspace time zone. */
const DISPLAY_TIME_ZONE = "Asia/Gaza";

/**
 * Fixed zone, locale and digit format, so the server and the browser produce the
 * same text. `toLocaleString()` without them follows each machine's own settings,
 * and React then reports a hydration mismatch for the page.
 */
const PARTS = new Intl.DateTimeFormat("en-CA", {
  timeZone: DISPLAY_TIME_ZONE,
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
  hour: "2-digit",
  minute: "2-digit",
  hourCycle: "h23",
});

function parts(iso: string): Record<string, string> {
  return Object.fromEntries(PARTS.formatToParts(new Date(iso)).map(({ type, value }) => [type, value]));
}

/** `2026-11-05` */
export function formatDate(iso: string): string {
  const { year, month, day } = parts(iso);
  return `${year}-${month}-${day}`;
}

/** `2026-11-05 00:30` */
export function formatDateTime(iso: string): string {
  const { hour, minute } = parts(iso);
  return `${formatDate(iso)} ${hour}:${minute}`;
}
