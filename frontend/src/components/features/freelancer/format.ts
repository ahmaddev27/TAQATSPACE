/**
 * Small presentation helpers shared by the freelancer screens. Kept local to
 * the feature so we don't touch the shared `lib`. Dates are rendered in a
 * stable ISO-like form (the prototype shows `2026-06-28`), which is also
 * locale-neutral and avoids hydration mismatches.
 */

/** Format a backend date/datetime string as `YYYY-MM-DD`; empty when null. */
export function formatDate(value: string | null | undefined): string {
  if (!value) return "—";
  // Backend sends ISO strings; take the date portion without timezone math.
  const datePart = value.slice(0, 10);
  return /^\d{4}-\d{2}-\d{2}$/.test(datePart) ? datePart : value;
}

/** Format a numeric/string amount with the shekel sign, trimming `.00`. */
export function formatMoney(
  amount: number | string | null | undefined,
  currency = "₪",
): string {
  if (amount == null) return `${currency}0`;
  const num = typeof amount === "string" ? Number(amount) : amount;
  if (Number.isNaN(num)) return `${currency}${amount}`;
  const rounded = Number.isInteger(num) ? num.toString() : num.toFixed(2);
  return `${currency}${rounded}`;
}
