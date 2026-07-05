import { setRequestLocale, getTranslations } from "next-intl/server";
import { PosTerminal } from "@/components/features/cashier/PosTerminal";
import {
  cashierPosProducts,
  cashierPosSummary,
  cashierPosOrders,
} from "@/lib/api/pos";

export const dynamic = "force-dynamic";

/**
 * Cashier point-of-sale terminal. Fetches the catalogue, open (pending) orders,
 * and the at-a-glance summary server-side, then hands them to the interactive
 * <PosTerminal>. The `/pos/*` endpoints resolve the workspace from the cashier's
 * account, so no workspace id is needed here.
 */
export default async function CashierPosPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("cashier.terminal");

  const [summary, productsResult, pendingResult] = await Promise.all([
    cashierPosSummary(),
    cashierPosProducts(),
    cashierPosOrders({ status: "pending", per_page: 50 }),
  ]);

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      <PosTerminal
        products={productsResult.products}
        pendingOrders={pendingResult.orders}
        summary={summary}
      />
    </div>
  );
}
