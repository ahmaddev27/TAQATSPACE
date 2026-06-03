import { Suspense } from "react";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { getOwnerStats } from "@/lib/api/ownerDashboard";
import { StatTile } from "@/components/ui/StatTile";
import { LineChart, type LineChartPoint } from "@/components/ui/LineChart";
import { Icon } from "@/components/ui/Icon";
import { money } from "@/components/features/owner/format";
import { OwnerInvoiceAlert } from "@/components/features/invoices/OwnerInvoiceAlert";
import type { IconName } from "@/components/ui/Icon";

export const dynamic = "force-dynamic";

interface QuickAction {
  href: string;
  icon: IconName;
  label: string;
}

export default async function OwnerDashboardPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.dashboard");

  const stats = await getOwnerStats();

  const chart: LineChartPoint[] = stats.revenue_chart.map((p) => ({
    label: p.month,
    v: p.amount,
  }));

  const revenueDelta = stats.revenue_this_month - stats.revenue_last_month;
  const deltaPct =
    stats.revenue_last_month > 0
      ? Math.round((revenueDelta / stats.revenue_last_month) * 100)
      : null;

  const quickActions: QuickAction[] = [
    { href: "/owner/members", icon: "users", label: t("qaMembers") },
    { href: "/owner/seats", icon: "grid", label: t("qaSeats") },
    { href: "/owner/bookings", icon: "inbox", label: t("qaBookings") },
    { href: "/owner/settings", icon: "settings", label: t("qaSettings") },
  ];

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
          <OwnerInvoiceAlert />
        </Suspense>

        <div className="grid-stats">
          <StatTile
            icon="grid"
            label={t("occupancy")}
            value={`${stats.occupancy_pct}%`}
            foot={t("occupancyFoot")}
          />
          <StatTile
            icon="users"
            label={t("activeMembers")}
            value={stats.active_members}
            foot={t("activeMembersFoot")}
          />
          <StatTile
            icon="grid"
            label={t("availableSeats")}
            value={stats.available_seats}
            foot={t("availableSeatsFoot")}
          />
          <StatTile
            icon="inbox"
            amber={stats.pending_bookings > 0}
            label={t("pendingBookings")}
            value={stats.pending_bookings}
            foot={t("pendingBookingsFoot")}
          />
          <StatTile
            icon="receipt"
            amber={stats.overdue_invoices > 0}
            label={t("overdueInvoices")}
            value={stats.overdue_invoices}
            foot={t("overdueInvoicesFoot")}
          />
        </div>

        <div className="dash-2col">
          <div className="card card-pad">
            <div className="between" style={{ marginBottom: 18 }}>
              <div>
                <h3 className="h3">{t("revenueTitle")}</h3>
                <p className="muted-3" style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}>
                  {t("revenueSubtitle")}
                </p>
              </div>
              {deltaPct != null && (
                <span
                  className={`badge ${deltaPct >= 0 ? "badge-success" : "badge-danger"}`}
                >
                  <Icon name={deltaPct >= 0 ? "trend" : "arrowDown"} size={13} />
                  {deltaPct >= 0 ? "+" : ""}
                  {deltaPct}%
                </span>
              )}
            </div>
            <LineChart data={chart} />
          </div>

          <div className="card card-pad stack" style={{ gap: 18 }}>
            <div>
              <h3 className="h3">{t("revenueThisMonth")}</h3>
              <div
                className="tnum"
                style={{ fontSize: "2rem", fontWeight: 700, marginTop: 6 }}
              >
                {money(stats.revenue_this_month)}
              </div>
              <p className="muted-3" style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}>
                {t("revenueVsLast")}: {money(stats.revenue_last_month)}
              </p>
            </div>

            <div className="divider" />

            <div className="stack" style={{ gap: 10 }}>
              <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
                {t("quickActions")}
              </h3>
              <div
                style={{
                  display: "grid",
                  gridTemplateColumns: "1fr 1fr",
                  gap: 10,
                }}
              >
                {quickActions.map((a) => (
                  <Link
                    key={a.href}
                    href={a.href}
                    className="card card-pad row"
                    style={{
                      gap: 10,
                      alignItems: "center",
                      textDecoration: "none",
                      color: "var(--text)",
                    }}
                  >
                    <span className="st-ico">
                      <Icon name={a.icon} />
                    </span>
                    <span style={{ fontSize: "var(--fs-sm)", fontWeight: 500 }}>
                      {a.label}
                    </span>
                  </Link>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
