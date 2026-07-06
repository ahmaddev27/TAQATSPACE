import { setRequestLocale, getTranslations } from "next-intl/server";
import { PosTerminal } from "@/components/features/cashier/PosTerminal";
import {
  ownerPosProducts,
  ownerPosSummary,
  ownerPosOpenOrders,
} from "@/lib/api/pos";
import type { PosSummary } from "@/lib/types/pos";

export const dynamic = "force-dynamic";

/**
 * Owner-side point-of-sale terminal. The owner runs the same POS as a cashier
 * (ring up walk-in sales, advance fulfillment, settle orders) — the `/pos/*`
 * endpoints resolve the workspace from the owner's account, so the cashier
 * <PosTerminal> is reused as-is with the owner readers.
 */
export default async function OwnerPosTerminalPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("cashier.terminal");

  const [productsResult, openOrders] = await Promise.all([
    ownerPosProducts(),
    ownerPosOpenOrders(),
  ]);

  // The summary needs `pos_view_reports`; keep the terminal resilient (owners
  // always have it, but a 403 just hides the stat tiles instead of erroring).
  let summary: PosSummary | null = null;
  try {
    summary = await ownerPosSummary();
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
