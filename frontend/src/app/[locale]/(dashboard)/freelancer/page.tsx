import { Suspense } from "react";
import { getTranslations } from "next-intl/server";
import { getMemberSummary } from "@/lib/api/memberDashboard";
import { Icon } from "@/components/ui/Icon";
import { StatTile } from "@/components/ui/StatTile";
import { NotificationBell } from "@/components/features/freelancer/NotificationBell";
import { NoSubscriptionPanel } from "@/components/features/freelancer/NoSubscriptionPanel";
import { QuickLinks } from "@/components/features/freelancer/QuickLinks";
import { formatDate, formatMoney } from "@/components/features/freelancer/format";
import { MemberInvoiceAlert } from "@/components/features/invoices/MemberInvoiceAlert";

export default async function FreelancerHomePage() {
  const [summary, t, tCommon] = await Promise.all([
    getMemberSummary(),
    getTranslations("freelancer.home"),
    getTranslations("common"),
  ]);

  const currency = tCommon("currency");
  const sub = summary.subscription;
  const isActive = sub?.status === "active";

  return (
    <div className="stack" style={{ gap: 24 }}>
      <Suspense fallback={null}>
        <MemberInvoiceAlert />
      </Suspense>

      <div className="between wrap" style={{ gap: 16 }}>
        <div className="stack" style={{ gap: 4 }}>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted">{t("subtitle")}</p>
        </div>
        <NotificationBell unread={summary.unread_notifications} />
      </div>

      {!isActive ? (
        <NoSubscriptionPanel
          pendingRequests={summary.pending_booking_requests}
        />
      ) : (
        <>
          <div className="dash-2col">
            {/* Subscription status hero */}
            <div
              className="card card-pad"
              style={{
                background:
                  "linear-gradient(135deg, var(--blue-600), var(--blue-800))",
                color: "#fff",
                borderColor: "transparent",
              }}
            >
              <div className="between">
                <span
                  className="badge"
                  style={{ background: "rgba(255,255,255,.18)", color: "#fff" }}
                >
                  <span className="dot" />
                  {t("activeBadge")}
                </span>
                <Icon name="card" size={22} style={{ opacity: 0.7 }} />
              </div>
              <div style={{ marginTop: 18 }}>
                <div style={{ opacity: 0.8, fontSize: "var(--fs-sm)" }}>
                  {t("currentPlan")}
                </div>
                <div className="h1" style={{ color: "#fff", marginTop: 4 }}>
                  {tCommon(`planType.${sub.plan_type}`)}
                </div>
              </div>
              <div
                className="row wrap"
                style={{
                  gap: 32,
                  marginTop: 20,
                  paddingTop: 18,
                  borderTop: "1px solid rgba(255,255,255,.18)",
                }}
              >
                <div>
                  <div style={{ opacity: 0.8, fontSize: "var(--fs-xs)" }}>
                    {t("space")}
                  </div>
                  <div style={{ fontWeight: 600, marginTop: 2 }}>
                    {sub.workspace_name}
                  </div>
                </div>
                <div>
                  <div style={{ opacity: 0.8, fontSize: "var(--fs-xs)" }}>
                    {t("renewsOn")}
                  </div>
                  <div
                    className="tnum ltr"
                    style={{ fontWeight: 600, marginTop: 2 }}
                  >
                    {formatDate(sub.end_date)}
                  </div>
                </div>
              </div>
            </div>

            {/* Assigned seat */}
            <div className="card card-pad stack" style={{ gap: 14 }}>
              <h3 className="h3">{t("yourSeat")}</h3>
              {summary.seat ? (
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
                    {summary.seat.seat_number}
                  </div>
                  <div className="stack" style={{ gap: 8 }}>
                    <div className="row muted" style={{ gap: 6 }}>
                      <Icon name="grid" size={15} />
                      {tCommon(`seatType.${summary.seat.type}`)}
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

          <div className="dash-3col">
            <StatTile
              icon="card"
              label={t("planLabel")}
              value={tCommon(`planType.${sub.plan_type}`)}
              foot={sub.workspace_name}
            />
            <StatTile
              icon="receipt"
              amber
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
  );
}
