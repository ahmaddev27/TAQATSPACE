import { getTranslations, setRequestLocale } from "next-intl/server";
import { getAllAdminUsers } from "@/lib/api/admin";
import { UsersTable } from "@/components/features/admin/UsersTable";

export const dynamic = "force-dynamic";

export default async function AdminUsersPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("admin.users");

  const users = await getAllAdminUsers();

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
        <UsersTable users={users} />
      </div>
    </div>
  );
}
