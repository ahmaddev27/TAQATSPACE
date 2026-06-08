import { redirect } from "next/navigation";

/**
 * Credentials are owned by the unified SSO system — there is no local password
 * to reset. This legacy route now redirects to the SSO launch screen.
 */
export default async function ResetPasswordPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  redirect(`/${locale}/login`);
}
