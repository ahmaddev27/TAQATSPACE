import { cookies } from "next/headers";
import { notFound, redirect } from "next/navigation";
import { ApiError } from "@/lib/api";
import { showWorkspaceBySlug } from "@/lib/api/workspaces";
import { getOwnerWorkspace } from "@/lib/actions/owner";
import { ROLE_COOKIE, TOKEN_COOKIE } from "@/lib/auth";

export const dynamic = "force-dynamic";

/** True when the visitor is a signed-in owner whose own workspace has this slug. */
async function isOwnerOf(slug: string): Promise<boolean> {
  const store = await cookies();
  const signedInOwner =
    !!store.get(TOKEN_COOKIE)?.value &&
    store.get(ROLE_COOKIE)?.value === "workspace_owner";
  if (!signedInOwner) return false;

  const own = await getOwnerWorkspace();
  return own?.slug === slug;
}

/**
 * Short workspace link: the owner lands on their dashboard, everyone else on
 * the public workspace page; an unknown (or unpublished) slug is a 404.
 */
export default async function WorkspaceSlugPage({
  params,
}: {
  params: Promise<{ locale: string; slug: string }>;
}) {
  const { locale, slug } = await params;

  if (await isOwnerOf(slug)) redirect(`/${locale}/owner`);

  let id: string;
  try {
    id = (await showWorkspaceBySlug(slug)).id;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }
  redirect(`/${locale}/workspaces/${id}`);
}
