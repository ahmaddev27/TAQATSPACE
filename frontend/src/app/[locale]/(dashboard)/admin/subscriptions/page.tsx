import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getAllAdminSubscriptions } from "@/lib/api/admin";
import { SubscriptionsTable } from "@/components/features/admin/SubscriptionsTable";

export const dynamic = "force-dynamic";

export default async function AdminSubscriptionsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_billing");
  const t = await getTranslations("admin.subscriptions");

  const subscriptions = await getAllAdminSubscriptions();

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
        <SubscriptionsTable subscriptions={subscriptions} />
      </div>
    </div>
  );
}
