import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getAdminLanding } from "@/lib/api/landing";
import { listWorkspaces } from "@/lib/api/workspaces";
import {
  LandingEditor,
  type WorkspaceOption,
} from "@/components/features/admin/LandingEditor";
import type { LandingContent } from "@/lib/types";

export const dynamic = "force-dynamic";

export default async function AdminLandingPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_content");
  const t = await getTranslations("admin.landing");

  // A CMS failure must not break the page; fall back to an empty draft.
  let content: LandingContent = {};
  try {
    content = await getAdminLanding();
  } catch {
    content = {};
  }

  // Active workspaces feed the "featured" picker (id + name only).
  let workspaces: WorkspaceOption[] = [];
  try {
    const page = await listWorkspaces({ page: 1 });
    workspaces = page.data.map((w) => ({ id: w.id, name: w.name }));
  } catch {
    workspaces = [];
  }

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
        <LandingEditor initial={content} workspaces={workspaces} />
      </div>
    </div>
  );
}
