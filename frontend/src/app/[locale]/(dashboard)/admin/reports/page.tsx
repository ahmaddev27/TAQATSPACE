import { getTranslations, setRequestLocale } from "next-intl/server";
import { getAdminReports } from "@/lib/api/admin";
import { ReportsDashboard } from "@/components/features/admin/ReportsDashboard";

export const dynamic = "force-dynamic";

export default async function AdminReportsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("admin.reports");

  const reports = await getAdminReports();

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
    </div>
  );
}
