import { setRequestLocale } from "next-intl/server";
import { TwoFactorChallengeForm } from "@/components/features/auth/TwoFactorChallengeForm";

/** Post-login 2FA challenge (password and SSO sign-ins both land here). */
export default async function TwoFactorChallengePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  return (
    <div className="auth-center">
      <TwoFactorChallengeForm />
    </div>
  );
}
