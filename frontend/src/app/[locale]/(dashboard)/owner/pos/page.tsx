import { setRequestLocale, getTranslations } from "next-intl/server";
import { StatTile } from "@/components/ui/StatTile";
import { Badge } from "@/components/ui/Badge";
import { PosManager } from "@/components/features/owner/PosManager";
import { money, shortDate } from "@/components/features/owner/format";
import {
  ownerPosProducts,
  ownerPosSummary,
  ownerPosOrders,
} from "@/lib/api/pos";
import type { PosOrderStatus } from "@/lib/types/pos";

export const dynamic = "force-dynamic";

/** Badge tone for each order lifecycle state. */
const ORDER_TONE: Record<PosOrderStatus, "success" | "warning" | "neutral"> = {
  paid: "success",
  pending: "warning",
  cancelled: "neutral",
};

export default async function OwnerPosPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.pos");

  const [summary, productsResult, ordersResult] = await Promise.all([
    ownerPosSummary(),
    ownerPosProducts(),
    ownerPosOrders({ per_page: 10 }),
  ]);

  const orders = ordersResult.orders;

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      <div className="stack" style={{ gap: 24 }}>
        <div className="grid-stats">
          <StatTile
            icon="wallet"
            label={t("summary.todaySales")}
            value={money(summary.today_sales)}
            foot={t("summary.todaySalesFoot")}
          />
          <StatTile
            icon="receipt"
            label={t("summary.todayOrders")}
            value={summary.today_orders}
            foot={t("summary.todayOrdersFoot")}
          />
          <StatTile
            icon="inbox"
            amber={summary.pending_orders > 0}
            label={t("summary.pendingOrders")}
            value={summary.pending_orders}
            foot={t("summary.pendingOrdersFoot")}
          />
          <StatTile
            icon="layers"
            amber={summary.low_stock > 0}
            label={t("summary.lowStock")}
            value={summary.low_stock}
            foot={t("summary.lowStockFoot")}
          />
        </div>

        <PosManager products={productsResult.products} />

        <section className="card card-pad stack" style={{ gap: 12 }}>
          <div className="stack" style={{ gap: 2 }}>
            <h3 className="h3" style={{ margin: 0 }}>{t("recentOrders")}</h3>
            <p className="muted-3" style={{ margin: 0, fontSize: "var(--fs-sm)" }}>
              {t("recentOrdersSubtitle")}
            </p>
          </div>

          {orders.length === 0 ? (
            <p className="muted" style={{ margin: 0 }}>{t("noOrders")}</p>
          ) : (
            <div className="stack" style={{ gap: 10 }}>
              {orders.map((o) => (
                <div key={o.id} className="between row wrap" style={{ gap: 10 }}>
                  <div className="stack" style={{ gap: 4 }}>
                    <div className="row wrap" style={{ gap: 8, alignItems: "center" }}>
                      <span className="ltr" style={{ fontWeight: 600 }}>
                        {o.order_number}
                      </span>
                      <Badge tone={ORDER_TONE[o.status]} dot>
                        {t(`orderStatus.${o.status}`)}
                      </Badge>
                    </div>
                    <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                      {o.customer_name ?? o.member?.name ?? t("walkIn")}
                      {" · "}
                      {shortDate(o.paid_at ?? o.created_at)}
                    </span>
                  </div>
                  <span className="tnum" style={{ fontWeight: 600 }}>
                    {money(o.total)}
                  </span>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
