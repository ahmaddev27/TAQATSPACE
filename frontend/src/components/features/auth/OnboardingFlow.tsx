"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { dashboardFor } from "@/lib/auth";
import { Icon } from "@/components/ui/Icon";
import type { User, UserRole } from "@/lib/types/auth";
import { FreelancerOnboardingForm } from "./FreelancerOnboardingForm";
import { OwnerOnboardingForm } from "./OwnerOnboardingForm";
import { PendingReviewScreen } from "./PendingReviewScreen";
import { CashierInviteCard } from "./CashierInviteCard";

/** Shape returned by POST /api/auth/onboarding on success. */
export interface OnboardingResult {
  user: User;
  role: UserRole;
  next: "dashboard" | "pending";
}

type Choice = "freelancer" | "workspace_owner";

/**
 * SSO role-selection onboarding. The user picks freelancer vs workspace-owner,
 * completes the matching minimal data, and is routed onward:
 *   - freelancer      -> their dashboard (account now active).
 *   - workspace_owner  -> pending-review screen (awaiting admin approval).
 */
export function OnboardingFlow() {
  const t = useTranslations("auth.onboarding");
  const router = useRouter();
  const { user, isLoading, refreshUser } = useAuth();

  const [choice, setChoice] = useState<Choice | null>(null);
  const [ownerDone, setOwnerDone] = useState(false);

  // A pending cashier invitation preempts the role picker: accepting turns the
  // account into a cashier, declining clears the invite and reveals the picker.
  const invitation = user?.pending_cashier_invitation ?? null;

  // Wait for the session to hydrate before deciding what to show — otherwise the
  // role picker flashes for a moment before a pending invitation resolves.
  if (isLoading) {
    return (
      <div className="reg-wrap" style={{ display: "grid", placeItems: "center", minHeight: 240 }}>
        <span className="btn-spinner" aria-hidden="true" />
      </div>
    );
  }

  async function handleDone(result: OnboardingResult) {
    if (result.next === "pending") {
      setOwnerDone(true);
      return;
    }
    // Freelancer is active — sync the session and land on the dashboard.
    await refreshUser();
    router.replace(dashboardFor(result.role));
  }

  if (ownerDone) return <PendingReviewScreen />;

  return (
    <div className="reg-wrap">
      <div style={{ textAlign: "center", marginBottom: 28 }}>
        <span className="eyebrow">{t("eyebrow")}</span>
        <h1 className="h1" style={{ marginTop: 8 }}>
          {t("title")}
        </h1>
        <p className="muted" style={{ marginTop: 8 }}>
          {t("subtitle")}
        </p>
      </div>

      {invitation && <CashierInviteCard invitation={invitation} />}

      {!invitation && choice === null && (
        <div className="onboarding-choices">
          <button
            type="button"
            className="onboarding-card"
            onClick={() => setChoice("freelancer")}
          >
            <span className="onboarding-card-icon">
              <Icon name="briefcase" size={26} />
            </span>
            <span className="onboarding-card-title">{t("freelancer.title")}</span>
            <span className="onboarding-card-desc">{t("freelancer.desc")}</span>
            <span className="onboarding-card-cta">
              {t("choose")}
              <Icon name="arrowR" size={18} />
            </span>
          </button>

          <button
            type="button"
            className="onboarding-card"
            onClick={() => setChoice("workspace_owner")}
          >
            <span className="onboarding-card-icon">
              <Icon name="building" size={26} />
            </span>
            <span className="onboarding-card-title">{t("owner.title")}</span>
            <span className="onboarding-card-desc">{t("owner.desc")}</span>
            <span className="onboarding-card-cta">
              {t("choose")}
              <Icon name="arrowR" size={18} />
            </span>
          </button>
        </div>
      )}

      {choice === "freelancer" && (
        <FreelancerOnboardingForm
          defaultPhone={user?.phone ?? ""}
          onBack={() => setChoice(null)}
          onDone={handleDone}
        />
      )}

      {choice === "workspace_owner" && (
        <OwnerOnboardingForm
          defaultPhone={user?.phone ?? ""}
          onBack={() => setChoice(null)}
          onDone={handleDone}
        />
      )}
    </div>
  );
}
