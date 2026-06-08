import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getAdminAnalytics, getAdminReports } from "@/lib/api/admin";
import { ReportsDashboard } from "@/components/features/admin/ReportsDashboard";
import { AnalyticsDashboard } from "@/components/features/admin/AnalyticsDashboard";

export const dynamic = "force-dynamic";

export default async function AdminReportsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "view_reports");
  const t = await getTranslations("admin.reports");
  const ta = await getTranslations("admin.analytics");

  const [reports, analytics] = await Promise.all([
    getAdminReports(),
    getAdminAnalytics(),
  ]);

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

      <ReportsDashboard data={reports} />

      <div className="page-head" style={{ marginTop: 16 }}>
        <div>
          <h2 className="h2">{ta("title")}</h2>
          <p className="muted" style={{ marginTop: 5 }}>
            {ta("subtitle")}
          </p>
        </div>
      </div>

      <AnalyticsDashboard data={analytics} />
    </div>
  );
}
