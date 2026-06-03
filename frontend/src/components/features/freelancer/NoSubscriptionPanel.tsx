"use client";

import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";

/**
 * Empty-state hero shown when the member has no active subscription: a prominent
 * "Explore Workspaces" CTA plus, when relevant, a pending booking-request notice.
 */
export function NoSubscriptionPanel({
  pendingRequests,
}: {
  pendingRequests: number;
}) {
  const t = useTranslations("freelancer.home");
  const router = useRouter();

  return (
    <div className="stack" style={{ gap: 18 }}>
      {pendingRequests > 0 && (
        <Alert tone="warning" icon="clock" title={t("pendingTitle")}>
          {t("pendingBody", { count: pendingRequests })}
        </Alert>
      )}

      <div
        className="card card-pad stack"
        style={{ gap: 16, alignItems: "center", textAlign: "center", padding: 40 }}
      >
        <span
          className="st-ico"
          style={{ width: 64, height: 64, borderRadius: "var(--r-tile)" }}
        >
          <Icon name="search" size={30} />
        </span>
        <div className="stack" style={{ gap: 6 }}>
          <h2 className="h2">{t("noSubTitle")}</h2>
          <p className="muted" style={{ maxWidth: 460, lineHeight: 1.7 }}>
            {t("noSubBody")}
          </p>
        </div>
        <Button
          variant="primary"
          size="lg"
          icon="search"
          onClick={() => router.push("/freelancer/explore")}
        >
          {t("exploreCta")}
        </Button>
      </div>
    </div>
  );
}
