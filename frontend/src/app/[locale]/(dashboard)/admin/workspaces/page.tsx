import { getTranslations, setRequestLocale } from "next-intl/server";
import { getAllAdminWorkspaces } from "@/lib/api/admin";
import { WorkspacesTable } from "@/components/features/admin/WorkspacesTable";
import { ExportCsvLink } from "@/components/features/admin/ExportCsvLink";

export const dynamic = "force-dynamic";

export default async function AdminWorkspacesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
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
        <ExportCsvLink type="workspaces" label={t("exportCsv")} />
      </div>

      <div style={{ marginTop: 16 }}>
        <WorkspacesTable workspaces={workspaces} />
      </div>
    </div>
  );
}
