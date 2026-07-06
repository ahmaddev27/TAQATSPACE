import { getTranslations, setRequestLocale } from "next-intl/server";
import { PosReports } from "@/components/features/pos/PosReports";
import { cashierPosReport } from "@/lib/api/pos";
import type { PosReport } from "@/lib/types/pos";

export const dynamic = "force-dynamic";

/**
 * Cashier POS sales report. The reports endpoint is gated by `pos_view_reports`;
 * a cashier without it gets a 403, which we degrade to a "no access" card so the
 * page never crashes. Cashiers with the grant see the same view owners do.
 */
export default async function CashierReportsPage({
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
    report = await cashierPosReport({ from, to });
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
