"use client";

import { useSyncExternalStore, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";

/** No-op subscribe: dismissal only changes via this component's own button. */
const noopSubscribe = () => () => {};

export interface InvoiceAlertProps {
  /** Number of overdue invoices. */
  overdue: number;
  /** Number of pending (unpaid, not yet overdue) invoices. */
  pending: number;
  /** Localized, locale-prefixed invoices route, e.g. `/owner/invoices`. */
  href: string;
  /** Owner sees the combined "X overdue · Y pending"; member sees a softer copy. */
  variant: "owner" | "member";
  /** Stable key used to remember dismissal for this session. */
  dismissKey: string;
}

/**
 * Dismissible banner shown at the top of a dashboard when invoices need
 * attention. Renders nothing when there are no overdue/pending invoices, or
 * after the user dismisses it for the session.
 *
 * Dismissal is tracked in `sessionStorage` (per browser tab) so the banner
 * stays hidden during the visit but returns on the next session.
 */
export function InvoiceAlert({
  overdue,
  pending,
  href,
  variant,
  dismissKey,
}: InvoiceAlertProps) {
  const t = useTranslations("invoices.alert");
  const total = overdue + pending;
  const storageKey = `taqat:invoiceAlert:${dismissKey}`;

  // Read the per-session dismissal from sessionStorage without a setState-in-
  // effect. The server snapshot is `false` (never dismissed) so SSR renders the
  // banner; the client snapshot reflects the real stored value after hydration.
  const storedDismissed = useSyncExternalStore(
    noopSubscribe,
    () => {
      try {
        return sessionStorage.getItem(storageKey) === "1";
      } catch {
        return false;
      }
    },
    () => false,
  );

  const [locallyDismissed, setLocallyDismissed] = useState(false);

  if (total === 0 || storedDismissed || locallyDismissed) return null;

  const dismiss = () => {
    try {
      sessionStorage.setItem(storageKey, "1");
    } catch {
      // Ignore storage failures (e.g. private mode); hide for this render.
    }
    setLocallyDismissed(true);
  };

  const tone = overdue > 0 ? "danger" : "warning";
  const icon = overdue > 0 ? "alert" : "clock";

  const title =
    variant === "owner"
      ? t("ownerTitle", { overdue, pending })
      : overdue > 0
        ? t("memberOverdueTitle", { count: overdue })
        : t("memberPendingTitle", { count: pending });

  const body = variant === "owner" ? t("ownerBody") : t("memberBody");

  return (
    <div className={`alert alert-${tone}`} role="status">
      <span className="alert-ico">
        <Icon name={icon} size={18} />
      </span>
      <div className="grow">
        <div className="alert-title">{title}</div>
        <div className="alert-body">{body}</div>
      </div>
      <div className="row" style={{ gap: 8, flex: "none" }}>
        <Link href={href} className="btn btn-secondary btn-sm">
          {t("view")}
        </Link>
        <button
          type="button"
          className="icon-btn"
          onClick={dismiss}
          aria-label={t("dismiss")}
        >
          <Icon name="x" size={16} />
        </button>
      </div>
    </div>
  );
}
