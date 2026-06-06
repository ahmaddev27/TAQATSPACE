/** Local presentation helpers for the super-admin tables (no shared-lib edits). */

/** Format a backend date/datetime string as `YYYY-MM-DD`; em dash when empty. */
export function adminDate(value: string | null | undefined): string {
  if (!value) return "—";
  const datePart = value.slice(0, 10);
  return /^\d{4}-\d{2}-\d{2}$/.test(datePart) ? datePart : value;
}

/** First letter of a name, upper-cased; falls back to "?". */
export function nameInitial(name: string | null | undefined): string {
  const trimmed = name?.trim();
  return trimmed ? trimmed.charAt(0).toUpperCase() : "?";
}
