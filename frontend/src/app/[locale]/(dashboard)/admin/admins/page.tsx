import { getTranslations, setRequestLocale } from "next-intl/server";
import { getAllManagedAdmins } from "@/lib/api/admin";
import { requireAdminPermission } from "@/lib/admin-guard";
import { AdminsTable } from "@/components/features/admin/AdminsTable";

export const dynamic = "force-dynamic";

export default async function AdminManagementPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("admin.admins");

  const me = await requireAdminPermission(locale, "manage_admins");
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
        <AdminsTable admins={admins} currentUserId={String(me.id)} />
      </div>
    </div>
  );
}
