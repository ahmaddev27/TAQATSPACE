"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { LandingContent } from "@/lib/types";

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
