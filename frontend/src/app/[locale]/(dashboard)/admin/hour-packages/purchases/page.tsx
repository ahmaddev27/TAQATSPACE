import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getHourPurchases } from "@/lib/api/hourPackages";
import { HourPurchasesTable } from "@/components/features/admin/HourPurchasesTable";

export const dynamic = "force-dynamic";

export default async function AdminHourPurchasesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_billing");
  const t = await getTranslations("admin.hourPurchases");

  const queue = await getHourPurchases();

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
      </div>

      <div style={{ marginTop: 16 }}>
        <HourPurchasesTable purchases={queue.data} stats={queue.stats} />
      </div>
    </div>
  );
}
