import { cn } from "@/lib/cn";
import { STATUS_MAP } from "@/components/ui/Badge";

/**
 * Invoice status badge. Identical to the shared StatusBadge except an unpaid
 * invoice (`pending`) reads "غير مدفوعة"/"Unpaid" — the shared map keeps
 * `pending` as "under review" for booking requests, which must not change.
 */
const INVOICE_OVERRIDES: Record<string, { cls: string; ar: string; en: string }> = {
  pending: { cls: "badge-neutral", ar: "غير مدفوعة", en: "Unpaid" },
  void: { cls: "badge-neutral", ar: "ملغاة", en: "Voided" },
  draft: { cls: "badge-neutral", ar: "مسودة", en: "Draft" },
  payment_rejected: { cls: "badge-danger", ar: "وصل مرفوض", en: "Receipt rejected" },
  partially_paid: { cls: "badge-warning", ar: "مدفوعة جزئياً", en: "Partially paid" },
};

export interface InvoiceStatusBadgeProps {
  status: string;
  /** Finance v2 lifecycle; a draft or voided invoice shows that instead of its payment status. */
  docStatus?: string;
  locale?: string;
  dot?: boolean;
}

export function InvoiceStatusBadge({
  status,
  docStatus,
  locale = "ar",
  dot = true,
}: InvoiceStatusBadgeProps) {
  const key = docStatus === "void" || docStatus === "draft" ? docStatus : status;
  const m =
    INVOICE_OVERRIDES[key] ??
    STATUS_MAP[key] ?? { cls: "badge-neutral", ar: key, en: key };
  return (
    <span className={cn("badge", m.cls)}>
      {dot && <span className="dot" />}
      {locale === "ar" ? m.ar : m.en}
    </span>
  );
}
