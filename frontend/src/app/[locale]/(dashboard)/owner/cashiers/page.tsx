import { setRequestLocale, getTranslations } from "next-intl/server";
import { ownerCashiers } from "@/lib/api/cashiers";
import { CashiersManager } from "@/components/features/owner/CashiersManager";

export const dynamic = "force-dynamic";

export default async function CashiersPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.cashiers");
  const { cashiers, invitations } = await ownerCashiers();

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>
      <CashiersManager cashiers={cashiers} invitations={invitations} />
    </div>
  );
}
