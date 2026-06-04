"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { LandingContent } from "@/lib/types";
import type { ContentByKey } from "@/lib/api/content";
import type { ContentKey } from "@/lib/types";

/**
 * Persist the public landing page content (`PUT /admin/landing`).
 *
 * On success, revalidate both the admin editor page and the public landing
 * page (across locales via the `[locale]` layout segment) so the new copy is
 * served immediately.
 */
export async function updateLanding(
  content: LandingContent,
): Promise<ActionResult<LandingContent>> {
  const result = await authedMutate<LandingContent>("/admin/landing", {
    method: "PUT",
    body: content,
  });

  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/admin/landing", "page");
    revalidatePath("/[locale]/(public)", "page");
  }

  return result;
}

/**
 * Public path each content key feeds into. The home page (`/[locale]/(public)`)
 * also reads `how_it_works`, and the footer (shared layout) reads `site`, so
 * those keys revalidate the public segment root in addition to their own route.
 */
const CONTENT_REVALIDATE: Record<ContentKey, string[]> = {
  site: ["/[locale]/(public)", "/[locale]/(public)/contact"],
  faq: ["/[locale]/(public)/faq"],
  about: ["/[locale]/(public)/about"],
  how_it_works: ["/[locale]/(public)"],
};

/**
 * Persist a site-wide content blob (`PUT /admin/content/{key}`).
 *
 * The backend expects the blob wrapped as `{ content }`. On success we
 * revalidate the admin "rm" hub plus every public path that renders this key so
 * the new copy is served immediately.
 */
export async function updateContent<K extends ContentKey>(
  key: K,
  content: ContentByKey[K],
): Promise<ActionResult<ContentByKey[K]>> {
  const result = await authedMutate<ContentByKey[K]>(`/admin/content/${key}`, {
    method: "PUT",
    body: { content },
  });

  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/admin/rm", "page");
    for (const path of CONTENT_REVALIDATE[key]) {
      revalidatePath(path, "page");
    }
  }

  return result;
}
