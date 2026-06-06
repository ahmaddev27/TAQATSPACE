import { getTranslations, setRequestLocale } from "next-intl/server";
import { ownerSubscriptions } from "@/lib/api/ownerSubscriptions";
import { SubscriptionsTable } from "@/components/features/owner/SubscriptionsTable";

export const dynamic = "force-dynamic";

export default async function OwnerSubscriptionsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.subscriptions");

  const { subscriptions } = await ownerSubscriptions({
    status: "all",
    per_page: 200,
  });

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
