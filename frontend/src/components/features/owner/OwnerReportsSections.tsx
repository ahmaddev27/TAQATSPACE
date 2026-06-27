import { getTranslations } from "next-intl/server";
import { StatTile } from "@/components/ui/StatTile";
import { money } from "./format";
import type { OwnerReports } from "@/lib/api/ownerReports";

export interface OwnerReportsSectionsProps {
  reports: OwnerReports;
}

/**
 * The deeper owner-report blocks: collections aging, a monthly profit-and-loss,
 * and internet-package uptake.
 */
export async function OwnerReportsSections({ reports }: OwnerReportsSectionsProps) {
  const t = await getTranslations("owner.reports");

  return (
    <>
      {/* Collections aging */}
      <div className="card card-pad stack" style={{ gap: 14 }}>
        <div className="between">
          <div>
            <h3 className="h3">{t("agingTitle")}</h3>
            <p className="muted-3" style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}>
              {t("agingSubtitle")}
            </p>
          </div>
          <div className="tnum" style={{ fontWeight: 700, fontSize: "1.2rem" }}>
            {money(reports.aging_total)}
          </div>
        </div>
        <div className="grid-stats grid-stats--5">
          {reports.aging.map((b) => (
            <StatTile
              key={b.key}
              icon={b.key === "current" ? "receipt" : "alert"}
              amber={b.key !== "current" && Number(b.amount) > 0}
              label={t(`aging.${b.key}`)}
              value={money(b.amount)}
              foot={t("agingCount", { count: b.count })}
            />
          ))}
        </div>
      </div>

      {/* Profit & loss */}
      <div className="card card-pad stack" style={{ gap: 14 }}>
        <div>
          <h3 className="h3">{t("plTitle")}</h3>
          <p className="muted-3" style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}>
            {t("plSubtitle")}
          </p>
        </div>
        <div className="table-wrap">
          <table className="tbl">
            <thead>
              <tr>
                <th>{t("plMonth")}</th>
                <th>{t("plRevenue")}</th>
                <th>{t("plExpenses")}</th>
                <th>{t("plNet")}</th>
              </tr>
            </thead>
            <tbody>
              {reports.profit_loss.map((m) => {
                const net = Number(m.net);
                return (
                  <tr key={m.month}>
                    <td className="cell-num ltr">{m.month}</td>
                    <td className="cell-num">{money(m.revenue)}</td>
                    <td className="cell-num" style={{ color: "var(--amber-600)" }}>
                      {money(m.expenses)}
                    </td>
                    <td
                      className="cell-num"
                      style={{
                        fontWeight: 700,
                        color: net < 0 ? "var(--danger)" : "var(--ok, #1B8A4B)",
                      }}
                    >
                      {money(m.net)}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Internet-package uptake */}
      <div className="card card-pad stack" style={{ gap: 14 }}>
        <div>
          <h3 className="h3">{t("packagesTitle")}</h3>
          <p className="muted-3" style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}>
            {t("packagesSubtitle")}
          </p>
        </div>
        {reports.package_uptake.length === 0 ? (
          <p className="muted">{t("packagesEmpty")}</p>
        ) : (
          <div className="table-wrap">
            <table className="tbl">
              <thead>
                <tr>
                  <th>{t("pkgName")}</th>
                  <th>{t("pkgPrice")}</th>
                  <th>{t("pkgMembers")}</th>
                </tr>
              </thead>
              <tbody>
                {reports.package_uptake.map((p) => (
                  <tr key={p.name}>
                    <td style={{ fontWeight: 600 }}>{p.name}</td>
                    <td className="cell-num">{money(p.price)}</td>
                    <td className="cell-num">{p.members}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </>
  );
}
