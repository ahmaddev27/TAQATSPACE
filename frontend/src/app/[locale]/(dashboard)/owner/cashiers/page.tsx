import { redirect } from "next/navigation";

export const dynamic = "force-dynamic";

/** Temporary redirect: the cashiers page became `/owner/settings/staff` (M1.3). */
export default async function LegacyCashiersPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  redirect(`/${locale}/owner/settings/staff`);
}
