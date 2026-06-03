"use client";

import { useLocale } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { PLANS, PlanCard } from "./PlanCard";

/**
 * Renders the static plan catalogue. Choosing a plan sends the member to the
 * explore flow, where a real booking against a workspace happens.
 */
export function AvailablePlansGrid({ currency }: { currency: string }) {
  const locale = useLocale();
  const router = useRouter();

  return (
    <div className="plan-grid">
      {PLANS.map((plan) => (
        <PlanCard
          key={plan.id}
          plan={plan}
          locale={locale}
          currency={currency}
          popularLabel={locale === "ar" ? "الأكثر اختياراً" : "Most popular"}
          chooseLabel={locale === "ar" ? "اختر الخطة" : "Choose plan"}
          perUnitLabel={(unit) => `/ ${unit}`}
          onChoose={() => router.push("/freelancer/explore")}
        />
      ))}
    </div>
  );
}
