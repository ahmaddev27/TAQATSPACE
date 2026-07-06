import { setRequestLocale, getTranslations } from "next-intl/server";
import { PosTerminal } from "@/components/features/cashier/PosTerminal";
import { ownerPosProducts, ownerPosSummary, ownerPosOrders } from "@/lib/api/pos";

export const dynamic = "force-dynamic";

/**
 * Owner-side point-of-sale terminal. The owner runs the same POS as a cashier
 * (ring up walk-in sales, settle freelancer-placed orders) — the `/pos/*`
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

  const [summary, productsResult, pendingResult] = await Promise.all([
    ownerPosSummary(),
    ownerPosProducts(),
    ownerPosOrders({ status: "pending", per_page: 50 }),
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
