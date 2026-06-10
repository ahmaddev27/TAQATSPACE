import { Suspense } from "react";
import { getTranslations, getLocale } from "next-intl/server";
import { getMemberSummary } from "@/lib/api/memberDashboard";
import { Icon } from "@/components/ui/Icon";
import { StatTile } from "@/components/ui/StatTile";
import { StatusBadge } from "@/components/ui/Badge";
import { NoSubscriptionPanel } from "@/components/features/freelancer/NoSubscriptionPanel";
import { QuickLinks } from "@/components/features/freelancer/QuickLinks";
import { formatDate, formatMoney } from "@/components/features/freelancer/format";
import { MemberInvoiceAlert } from "@/components/features/invoices/MemberInvoiceAlert";

export const dynamic = "force-dynamic";

export default async function FreelancerHomePage() {
  const [summary, t, tCommon, locale] = await Promise.all([
    getMemberSummary(),
    getTranslations("freelancer.home"),
    getTranslations("common"),
    getLocale(),
  ]);

  const currency = tCommon("currency");
  const sub = summary.subscription;
  const seat = summary.seat;
  const isActive = sub?.status === "active";

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
      </div>

      <div className="stack" style={{ gap: 24, marginTop: 8 }}>
        <Suspense fallback={null}>
          <MemberInvoiceAlert />
        </Suspense>

        {!isActive || !sub ? (
          <NoSubscriptionPanel
            pendingRequests={summary.pending_booking_requests}
          />
        ) : (
          <>
            <div className="dash-2col">
              {/* Subscription status card — seat type + workspace headline */}
              <div className="card card-pad stack" style={{ gap: 0 }}>
                <div className="between">
                  <span className="row" style={{ gap: 12 }}>
                    <span
                      className="st-ico"
                      style={{ width: 48, height: 48 }}
                    >
                      <Icon name="card" size={22} />
                    </span>
                    <span className="stack" style={{ gap: 2 }}>
                      <span
                        className="muted-3"
                        style={{ fontSize: "var(--fs-sm)" }}
                      >
                        {t("subscriptionLabel")}
                      </span>
                      <span className="h3">
                        {seat
                          ? tCommon(`seatType.${seat.type}`)
                          : sub.workspace_name}
                      </span>
                    </span>
                  </span>
                  <StatusBadge status={sub.status} locale={locale} />
                </div>

                <div className="divider" style={{ margin: "18px 0" }} />

                <div className="row wrap" style={{ gap: 28 }}>
                  <SummaryItem
                    icon="pin"
                    label={t("space")}
                    value={sub.workspace_name}
                  />
                  <SummaryItem
                    icon="calendar"
                    label={t("renewsOn")}
                    value={formatDate(sub.end_date)}
                    numeric
                  />
                </div>
              </div>

              {/* Assigned seat */}
              <div className="card card-pad stack" style={{ gap: 14 }}>
                <h3 className="h3">{t("yourSeat")}</h3>
                {seat ? (
                  <div className="row" style={{ gap: 18 }}>
                    <div
                      className="seat is-selected"
                      style={{
                        width: 84,
                        height: 84,
                        fontSize: "1.5rem",
                        flex: "none",
                        cursor: "default",
                      }}
                    >
                      {seat.seat_number}
                    </div>
                    <div className="stack" style={{ gap: 8 }}>
                      <div className="row muted" style={{ gap: 6 }}>
                        <Icon name="grid" size={15} />
                        {tCommon(`seatType.${seat.type}`)}
                      </div>
                      <div className="row muted" style={{ gap: 6 }}>
                        <Icon name="pin" size={15} />
                        {sub.workspace_name}
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="muted">{t("noSeat")}</p>
                )}
              </div>
            </div>

            <div className="grid-stats grid-stats--3">
              <StatTile
                icon="grid"
                label={t("seatType")}
                value={seat ? tCommon(`seatType.${seat.type}`) : "—"}
                foot={sub.workspace_name}
              />
              <StatTile
                icon="receipt"
                amber={Boolean(summary.next_invoice)}
                label={t("nextInvoice")}
                value={
                  summary.next_invoice
                    ? formatMoney(summary.next_invoice.amount, currency)
                    : "—"
                }
                foot={
                  summary.next_invoice
                    ? t("nextInvoiceDue", {
                        date: formatDate(summary.next_invoice.due_date),
                      })
                    : t("noInvoice")
                }
              />
              <StatTile
                icon="bell"
                label={tCommon("notifications")}
                value={summary.unread_notifications.toString()}
                foot={t("unreadBell", { count: summary.unread_notifications })}
              />
            </div>

            <div className="dash-2col">
              <div className="card card-pad">
                <div className="between" style={{ marginBottom: 16 }}>
                  <h3 className="h3">{t("notificationsTitle")}</h3>
                </div>
                <p
                  className="muted"
                  style={{ padding: "16px 0", textAlign: "center" }}
                >
                  {t("notificationsEmpty")}
                </p>
              </div>
              <QuickLinks hasSubscription={isActive} />
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function SummaryItem({
  icon,
  label,
  value,
  numeric,
}: {
  icon: "pin" | "calendar";
  label: string;
  value: string;
  numeric?: boolean;
}) {
  return (
    <div className="stack" style={{ gap: 4 }}>
      <span className="row muted-3" style={{ gap: 6, fontSize: "var(--fs-xs)" }}>
        <Icon name={icon} size={13} />
        {label}
      </span>
      <span
        className={numeric ? "tnum ltr" : undefined}
        style={{ fontWeight: 600 }}
      >
        {value}
      </span>
    </div>
  );
}
