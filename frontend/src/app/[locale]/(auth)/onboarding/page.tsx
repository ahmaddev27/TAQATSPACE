import { redirect } from "next/navigation";
import { setRequestLocale } from "next-intl/server";
import { serverFetch, ApiError } from "@/lib/api";
import { dashboardFor } from "@/lib/auth";
import { OnboardingFlow } from "@/components/features/auth/OnboardingFlow";
import type { ApiEnvelope, User } from "@/lib/types";

/**
 * SSO role-selection onboarding. Server-gated so the page reflects the account's
 * persisted state instead of transient client state: a user who has already
 * onboarded (e.g. after a page refresh) is routed to their dashboard rather than
 * being dropped back at the role chooser — the dashboard layout then renders the
 * pending/suspended notice for non-active accounts.
 */
export default async function OnboardingPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  let user: User;
  try {
    const res = await serverFetch<ApiEnvelope<{ user: User }>>("/auth/me");
    user = res.data.user;
  } catch (error) {
    // /onboarding isn't middleware-protected; guard the unauthenticated case.
    const status = error instanceof ApiError ? error.status : 401;
    if (status === 401 || status === 403) {
      redirect(`/${locale}/login`);
    }
    throw error;
  }

  // Already onboarded — the role + status now live on the account. Hand off to
  // the dashboard, which owns the active/pending/suspended gating.
  if (!user.needs_onboarding) {
    redirect(`/${locale}${dashboardFor(user.role)}`);
  }

  return (
    <div className="pub">
      <OnboardingFlow />
    </div>
  );
}
