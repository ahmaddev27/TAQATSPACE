import { getTranslations, setRequestLocale } from "next-intl/server";
import { getOwnerStats } from "@/lib/api/ownerDashboard";
import { LineChart, type LineChartPoint } from "@/components/ui/LineChart";
import { MemberAnalytics } from "@/components/features/owner/MemberAnalytics";
import { ExportCsvLink } from "@/components/features/admin/ExportCsvLink";
import { money } from "@/components/features/owner/format";

export const dynamic = "force-dynamic";

/**
 * Owner reports: revenue trend, member analytics (gender + status), and CSV
 * downloads of the workspace's members / invoices / expenses. Reuses the
 * dashboard's stats payload + analytics widget so the figures stay consistent.
 */
export default async function OwnerReportsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.reports");
  const ta = await getTranslations("owner.analytics");
  const td = await getTranslations("owner.dashboard");

  const stats = await getOwnerStats();
  const chart: LineChartPoint[] = stats.revenue_chart.map((p) => ({
    label: p.month,
    v: p.amount,
  }));

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
        {/* Revenue trend */}
        <div className="card card-pad">
          <div className="between" style={{ marginBottom: 18 }}>
            <div>
              <h3 className="h3">{td("revenueTitle")}</h3>
              <p
                className="muted-3"
                style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}
              >
                {td("revenueSubtitle")}
              </p>
            </div>
            <div className="tnum" style={{ fontWeight: 700, fontSize: "1.25rem" }}>
              {money(stats.revenue_this_month)}
            </div>
          </div>
          <LineChart data={chart} />
        </div>

        {/* Member analytics */}
        <div>
          <h3 className="h3">{ta("title")}</h3>
          <p
            className="muted-3"
            style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}
          >
            {ta("subtitle")}
          </p>
        </div>
        <MemberAnalytics
          byGender={stats.members_by_gender}
          byStatus={stats.members_by_status}
        />

        {/* CSV exports */}
        <div className="card card-pad stack" style={{ gap: 14 }}>
          <div>
            <h3 className="h3">{t("exportsTitle")}</h3>
            <p
              className="muted-3"
              style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}
            >
              {t("exportsSubtitle")}
            </p>
          </div>
          <div className="dash-3col">
            <ExportCsvLink
              type="members"
              label={t("exportMembers")}
              basePath="/api/owner/exports"
              variant="card"
            />
            <ExportCsvLink
              type="invoices"
              label={t("exportInvoices")}
              basePath="/api/owner/exports"
              variant="card"
            />
            <ExportCsvLink
              type="expenses"
              label={t("exportExpenses")}
              basePath="/api/owner/exports"
              variant="card"
            />
          </div>
        </div>
      </div>
    </div>
  );
}
