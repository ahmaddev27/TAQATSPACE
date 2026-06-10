import { cn } from "@/lib/cn";
import { STATUS_MAP } from "@/components/ui/Badge";

/**
 * Invoice status badge. Identical to the shared StatusBadge except an unpaid
 * invoice (`pending`) reads "غير مدفوعة"/"Unpaid" — the shared map keeps
 * `pending` as "under review" for booking requests, which must not change.
 */
const INVOICE_OVERRIDES: Record<string, { cls: string; ar: string; en: string }> = {
  pending: { cls: "badge-neutral", ar: "غير مدفوعة", en: "Unpaid" },
};

export interface InvoiceStatusBadgeProps {
  status: string;
  locale?: string;
  dot?: boolean;
}

export function InvoiceStatusBadge({
  status,
  locale = "ar",
  dot = true,
}: InvoiceStatusBadgeProps) {
  const m =
    INVOICE_OVERRIDES[status] ??
    STATUS_MAP[status] ?? { cls: "badge-neutral", ar: status, en: status };
  return (
    <span className={cn("badge", m.cls)}>
      {dot && <span className="dot" />}
      {locale === "ar" ? m.ar : m.en}
    </span>
  );
}
