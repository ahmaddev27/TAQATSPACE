/** Local presentation helpers for the invoicing screens (no shared-lib edits). */

/** Format a backend date string as `YYYY-MM-DD`; em dash when empty. */
export function invoiceDate(value: string | null | undefined): string {
  if (!value) return "—";
  const datePart = value.slice(0, 10);
  return /^\d{4}-\d{2}-\d{2}$/.test(datePart) ? datePart : value;
}

/** Format a decimal string/number amount with the shekel sign, trimming `.00`. */
export function invoiceMoney(
  amount: number | string | null | undefined,
  currency = "₪",
): string {
  if (amount == null) return `${currency}0`;
  const num = typeof amount === "string" ? Number(amount) : amount;
  if (Number.isNaN(num)) return `${currency}${amount}`;
  const rounded = Number.isInteger(num) ? String(num) : num.toFixed(2);
  return `${currency}${rounded}`;
}

/** Sum the `amount` field across a list of invoices (strings are parsed). */
export function sumAmounts(
  amounts: ReadonlyArray<string | number>,
): number {
  return amounts.reduce<number>((total, value) => {
    const num = typeof value === "string" ? Number(value) : value;
    return total + (Number.isNaN(num) ? 0 : num);
  }, 0);
}

/**
 * Derive the billing period label for an invoice from its subscription dates,
 * falling back to the invoice number when the chain isn't loaded.
 */
export function invoicePeriod(
  start: string | null | undefined,
  end: string | null | undefined,
): string {
  if (start && end) return `${invoiceDate(start)} → ${invoiceDate(end)}`;
  if (end) return invoiceDate(end);
  return "—";
}
