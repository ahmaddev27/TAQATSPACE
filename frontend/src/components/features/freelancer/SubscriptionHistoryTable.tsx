import { getTranslations } from "next-intl/server";
import { StatusBadge } from "@/components/ui/Badge";
import type { Subscription } from "@/lib/types";
import { formatDate, formatMoney } from "./format";

export interface SubscriptionHistoryTableProps {
  subscriptions: Subscription[];
  locale: string;
  currency: string;
}

/**
 * Read-only history of all subscriptions for the member. Server component:
 * ports the prototype `.tbl` table system (no client interactivity needed).
 */
export async function SubscriptionHistoryTable({
  subscriptions,
  locale,
  currency,
}: SubscriptionHistoryTableProps) {
  const t = await getTranslations("freelancer.subscription");
  const tCommon = await getTranslations("common");

  if (subscriptions.length === 0) {
    return (
      <div className="card card-pad">
        <p className="muted" style={{ textAlign: "center", padding: "16px 0" }}>
          {t("historyEmpty")}
        </p>
      </div>
    );
  }

  return (
    <div className="table-wrap">
      <table className="tbl">
        <thead>
          <tr>
            <th>{t("colWorkspace")}</th>
            <th>{t("colPlan")}</th>
            <th>{t("colPeriod")}</th>
            <th>{t("colPrice")}</th>
            <th>{t("colStatus")}</th>
          </tr>
        </thead>
        <tbody>
          {subscriptions.map((sub) => (
            <tr key={sub.id}>
              <td style={{ fontWeight: 600 }}>
                {sub.workspace?.name ?? "—"}
              </td>
              <td className="muted">{tCommon(`planType.${sub.plan_type}`)}</td>
              <td className="cell-num ltr">
                {formatDate(sub.start_date)} — {formatDate(sub.end_date)}
              </td>
              <td className="cell-num" style={{ fontWeight: 600 }}>
                {formatMoney(sub.monthly_price, currency)}
              </td>
              <td>
                <StatusBadge status={sub.status} locale={locale} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      <div className="table-foot">
        <span>{t("rowsCount", { count: subscriptions.length })}</span>
      </div>
    </div>
  );
}
