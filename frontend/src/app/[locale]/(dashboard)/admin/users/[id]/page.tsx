import { notFound } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { ApiError } from "@/lib/api";
import { getAdminUserDetail, type AdminUserDetail } from "@/lib/api/admin";
import { UserDetail } from "@/components/features/admin/UserDetail";

export const dynamic = "force-dynamic";

async function loadUser(id: string): Promise<AdminUserDetail | null> {
  try {
    return await getAdminUserDetail(id);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}

export default async function AdminUserDetailPage({
  params,
}: {
  params: Promise<{ locale: string; id: string }>;
}) {
  const { locale, id } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_users");
  const t = await getTranslations("admin.userDetail");

  const user = await loadUser(id);
  if (!user) notFound();

  return <UserDetail user={user} locale={locale} backLabel={t("back")} />;
}
