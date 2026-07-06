import { setRequestLocale, getTranslations } from "next-intl/server";
import { PosTerminal } from "@/components/features/cashier/PosTerminal";
import {
  cashierPosProducts,
  cashierPosSummary,
  cashierPosOpenOrders,
} from "@/lib/api/pos";
import type { PosSummary } from "@/lib/types/pos";

export const dynamic = "force-dynamic";

/**
 * Cashier point-of-sale terminal. Fetches the catalogue, open orders,
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

  const [productsResult, openOrders] = await Promise.all([
    cashierPosProducts(),
    cashierPosOpenOrders(),
  ]);

  // The summary needs `pos_view_reports`; a sell-only cashier must still reach
  // the terminal, so a 403 here just hides the stat tiles instead of erroring.
  let summary: PosSummary | null = null;
  try {
    summary = await cashierPosSummary();
  } catch {
    summary = null;
  }

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      <PosTerminal
        products={productsResult.products}
        openOrders={openOrders}
        summary={summary}
      />
    </div>
  );
}
