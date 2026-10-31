"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { ApprovalRequestEntry } from "@/lib/types/approvals";

function revalidateApprovals(): void {
  revalidatePath("/[locale]/(dashboard)/admin/approvals", "page");
}

/** Admin: approve a pending request (the backend applies the change once). */
export async function approveRequest(
  id: string,
): Promise<ActionResult<ApprovalRequestEntry>> {
  const result = await authedMutate<ApprovalRequestEntry>(
    `/admin/approvals/${encodeURIComponent(id)}/approve`,
  );
  if (result.ok) revalidateApprovals();
  return result;
}

/** Admin: reject a pending request; the reason is shown to the requester. */
export async function rejectRequest(
  id: string,
  reason: string,
): Promise<ActionResult<ApprovalRequestEntry>> {
  const result = await authedMutate<ApprovalRequestEntry>(
    `/admin/approvals/${encodeURIComponent(id)}/reject`,
    { body: { reason } },
  );
  if (result.ok) revalidateApprovals();
  return result;
}
