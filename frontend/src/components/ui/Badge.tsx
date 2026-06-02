import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

type Tone = "neutral" | "success" | "warning" | "danger" | "info";

export interface BadgeProps {
  tone?: Tone;
  dot?: boolean;
  children: ReactNode;
  className?: string;
}

export function Badge({ tone = "neutral", dot, children, className }: BadgeProps) {
  return (
    <span className={cn("badge", `badge-${tone}`, className)}>
      {dot && <span className="dot" />}
      {children}
    </span>
  );
}

/** Maps a backend status to its badge style + localized label. */
export const STATUS_MAP: Record<
  string,
  { cls: string; ar: string; en: string }
> = {
  active: { cls: "badge-success", ar: "نشط", en: "Active" },
  expired: { cls: "badge-neutral", ar: "منتهٍ", en: "Expired" },
  pending: { cls: "badge-warning", ar: "قيد المراجعة", en: "Pending" },
  pending_verification: { cls: "badge-warning", ar: "قيد المراجعة", en: "Pending" },
  approved: { cls: "badge-success", ar: "مقبول", en: "Approved" },
  rejected: { cls: "badge-danger", ar: "مرفوض", en: "Rejected" },
  suspended: { cls: "badge-danger", ar: "موقوف", en: "Suspended" },
  paid: { cls: "badge-success", ar: "مدفوعة", en: "Paid" },
  overdue: { cls: "badge-danger", ar: "متأخرة", en: "Overdue" },
};

export interface StatusBadgeProps {
  status: string;
  locale?: string;
  dot?: boolean;
}

export function StatusBadge({ status, locale = "ar", dot = true }: StatusBadgeProps) {
  const m = STATUS_MAP[status] ?? { cls: "badge-neutral", ar: status, en: status };
  return (
    <span className={cn("badge", m.cls)}>
      {dot && <span className="dot" />}
      {locale === "ar" ? m.ar : m.en}
    </span>
  );
}
