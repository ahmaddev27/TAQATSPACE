import { setRequestLocale } from "next-intl/server";
import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import {
  SecuritySettings,
  type TwoFactorStatus,
} from "@/components/features/security/SecuritySettings";

export const dynamic = "force-dynamic";

/** Account security: TOTP two-factor enrollment, recovery codes and disable. */
export default async function SecuritySettingsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const res = await serverFetch<ApiEnvelope<TwoFactorStatus>>("/auth/two-factor");

  return <SecuritySettings status={res.data} />;
}
