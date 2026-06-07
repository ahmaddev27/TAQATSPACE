import { redirect } from "next/navigation";

/**
 * Registration is SSO-only: account type is chosen post-login in onboarding.
 * This legacy route now redirects to the unified SSO launch screen.
 */
export default async function FreelancerRegisterPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  redirect(`/${locale}/login`);
}
