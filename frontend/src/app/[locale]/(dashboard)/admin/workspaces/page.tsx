import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getAllAdminWorkspaces } from "@/lib/api/admin";
import { WorkspacesTable } from "@/components/features/admin/WorkspacesTable";

export const dynamic = "force-dynamic";

export default async function AdminWorkspacesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_workspaces");
  const t = await getTranslations("admin.workspaces");

  const workspaces = await getAllAdminWorkspaces();

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
        <WorkspacesTable workspaces={workspaces} />
      </div>
    </div>
  );
}
