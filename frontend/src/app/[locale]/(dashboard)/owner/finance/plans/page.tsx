import { notFound } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { ApiError } from "@/lib/api";
import { ownerPlans } from "@/lib/api/financePlans";
import { PlanManager } from "@/components/features/owner/PlanManager";

export const dynamic = "force-dynamic";

export default async function OwnerPlansPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("finance.plans");

  const plans = await ownerPlans().catch((error: unknown) => {
    // The backend answers 404 while Finance v2 is off for the workspace.
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  });

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      <PlanManager plans={plans.items} />
    </div>
  );
}
