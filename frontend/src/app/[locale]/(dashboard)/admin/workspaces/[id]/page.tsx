import { notFound } from "next/navigation";
import { setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { ApiError } from "@/lib/api";
import {
  getAdminWorkspaceDetail,
  type AdminWorkspaceDetail,
} from "@/lib/api/admin";
import { WorkspaceDetail } from "@/components/features/admin/WorkspaceDetail";

export const dynamic = "force-dynamic";

async function loadWorkspace(id: string): Promise<AdminWorkspaceDetail | null> {
  try {
    return await getAdminWorkspaceDetail(id);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}

export default async function AdminWorkspaceDetailPage({
  params,
}: {
  params: Promise<{ locale: string; id: string }>;
}) {
  const { locale, id } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_workspaces");

  const workspace = await loadWorkspace(id);
  if (!workspace) notFound();

  return <WorkspaceDetail workspace={workspace} locale={locale} />;
}
