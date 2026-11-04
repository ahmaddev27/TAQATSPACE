import { getTranslations, setRequestLocale } from "next-intl/server";
import { ApiError } from "@/lib/api";
import { ownerPlans } from "@/lib/api/financePlans";
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
  // Plans answer 404 while Finance v2 is off; the terms form is then hidden.
  const plans = await ownerPlans()
    .then((result) => result.items)
    .catch((error: unknown) => {
      if (error instanceof ApiError && error.status === 404) return null;
      throw error;
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
        <SubscriptionsTable subscriptions={subscriptions} plans={plans} />
      </div>
    </div>
  );
}
