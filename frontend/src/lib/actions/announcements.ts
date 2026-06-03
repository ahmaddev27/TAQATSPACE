"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { Announcement, AnnouncementInput } from "@/lib/api/announcements";

/* -------------------------------------------------------------------------- */
/*  Revalidation                                                              */
/* -------------------------------------------------------------------------- */

/**
 * The announcements list lives under `/[locale]/(dashboard)/owner/announcements`.
 * Revalidating the page segment refreshes it regardless of the active locale.
 */
function revalidateAnnouncements(): void {
  revalidatePath(
    "/[locale]/(dashboard)/owner/announcements",
    "page",
  );
}

/* -------------------------------------------------------------------------- */
/*  Mutations                                                                  */
/* -------------------------------------------------------------------------- */

export async function createAnnouncement(
  input: AnnouncementInput,
): Promise<ActionResult<Announcement>> {
  const result = await authedMutate<Announcement>("/workspace/announcements", {
    method: "POST",
    body: input,
  });
  if (result.ok) revalidateAnnouncements();
  return result;
}

export async function updateAnnouncement(
  announcementId: string,
  input: Partial<AnnouncementInput>,
): Promise<ActionResult<Announcement>> {
  const result = await authedMutate<Announcement>(
    `/workspace/announcements/${announcementId}`,
    { method: "PUT", body: input },
  );
  if (result.ok) revalidateAnnouncements();
  return result;
}

export async function deleteAnnouncement(
  announcementId: string,
): Promise<ActionResult> {
  const result = await authedMutate(
    `/workspace/announcements/${announcementId}`,
    { method: "DELETE" },
  );
  if (result.ok) revalidateAnnouncements();
  return result;
}

/**
 * Publish a draft immediately by stamping `published_at` to "now" and clearing
 * any future schedule. A thin wrapper over `updateAnnouncement` so the list can
 * offer a one-click "Publish now" action without re-opening the form.
 */
export async function publishAnnouncement(
  announcementId: string,
): Promise<ActionResult<Announcement>> {
  return updateAnnouncement(announcementId, {
    published_at: new Date().toISOString(),
  });
}
