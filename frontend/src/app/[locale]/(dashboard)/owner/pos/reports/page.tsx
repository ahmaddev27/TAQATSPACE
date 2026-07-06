import { getTranslations, setRequestLocale } from "next-intl/server";
import { PosReports } from "@/components/features/pos/PosReports";
import { ownerPosReport } from "@/lib/api/pos";
import type { PosReport } from "@/lib/types/pos";

export const dynamic = "force-dynamic";

/**
 * Owner POS sales report: headline KPIs and breakdowns (top products, by
 * cashier, by payment method, refunds) for a date range. Owners always hold
 * `pos_view_reports`; a failed read still degrades to a "no access" card rather
 * than crashing the dashboard.
 */
export default async function OwnerPosReportsPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ from?: string; to?: string }>;
}) {
  const { locale } = await params;
  const { from, to } = await searchParams;
  setRequestLocale(locale);
  const t = await getTranslations("posReports");

  let report: PosReport | null = null;
  try {
    report = await ownerPosReport({ from, to });
  } catch {
    report = null;
  }

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      {report ? (
        <PosReports report={report} />
      ) : (
        <div className="card card-pad">
          <p className="muted" style={{ margin: 0 }}>{t("noAccess")}</p>
        </div>
      )}
    </div>
  );
}
