"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";

function revalidateInbox(): void {
  revalidatePath("/[locale]/(dashboard)/admin/messages", "page");
}

/** Admin: mark a contact message as read. */
export async function markContactRead(id: string): Promise<ActionResult> {
  const result = await authedMutate(`/admin/contact-messages/${id}/read`, {
    method: "PUT",
  });
  if (result.ok) revalidateInbox();
  return result;
}

/** Admin: delete a contact message. */
export async function deleteContactMessage(id: string): Promise<ActionResult> {
  const result = await authedMutate(`/admin/contact-messages/${id}`, {
    method: "DELETE",
  });
  if (result.ok) revalidateInbox();
  return result;
}
