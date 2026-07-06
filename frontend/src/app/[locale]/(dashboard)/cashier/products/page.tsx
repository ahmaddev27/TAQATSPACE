import { setRequestLocale, getTranslations } from "next-intl/server";
import { PosManager } from "@/components/features/owner/PosManager";
import { cashierPosProducts } from "@/lib/api/pos";

export const dynamic = "force-dynamic";

/**
 * Café product + inventory management for a cashier who holds the
 * `pos_manage_products` grant. Reuses the owner PosManager; the `/pos/*`
 * endpoints resolve the workspace from the cashier's account and enforce the
 * permission server-side.
 */
export default async function CashierProductsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.pos");
  const productsResult = await cashierPosProducts();

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("catalogueHeading")}</h1>
        <p className="muted">{t("catalogueSubtitle")}</p>
      </div>
      <PosManager products={productsResult.products} />
    </div>
  );
}
