import { redirect } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { serverFetch } from "@/lib/api";
import { getAllManagedAdmins } from "@/lib/api/admin";
import { AdminsTable } from "@/components/features/admin/AdminsTable";
import type { ApiEnvelope, User } from "@/lib/types";

export const dynamic = "force-dynamic";

export default async function AdminManagementPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("admin.admins");

  // Defense-in-depth: the backend gates the endpoints and the nav hides the
  // link, but a non-super-admin reaching this URL directly is bounced to the
  // admin home rather than shown an empty/erroring table.
  const me = await serverFetch<ApiEnvelope<{ user: User }>>("/auth/me");
  if (!me.data.user.is_super_admin) {
    redirect(`/${locale}/admin`);
  }

  const admins = await getAllManagedAdmins();

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
      </div>

      <div style={{ marginTop: 16 }}>
        <AdminsTable
          admins={admins}
          currentUserId={String(me.data.user.id)}
        />
      </div>
    </div>
  );
}
