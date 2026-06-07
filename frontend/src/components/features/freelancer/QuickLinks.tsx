"use client";

import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { Button } from "@/components/ui/Button";

/** Quick-action buttons on the freelancer home (client-side navigation). */
export function QuickLinks({ hasSubscription }: { hasSubscription: boolean }) {
  const t = useTranslations("freelancer.home");
  const router = useRouter();

  return (
    <div className="card card-pad stack" style={{ gap: 14 }}>
      <h3 className="h3">{t("quickActions")}</h3>
      {hasSubscription && (
        <Button
          variant="secondary"
          block
          icon="card"
          onClick={() => router.push("/freelancer/subscription")}
        >
          {t("manageSubscription")}
        </Button>
      )}
      <Button
        variant="secondary"
        block
        icon="user"
        onClick={() => router.push("/freelancer/profile")}
      >
        {t("editProfile")}
      </Button>
      <Button
        variant="secondary"
        block
        icon="search"
        onClick={() => router.push("/explore")}
      >
        {t("exploreOther")}
      </Button>
    </div>
  );
}
