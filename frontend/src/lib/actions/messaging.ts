"use server";

import { revalidatePath } from "next/cache";
import { ApiError } from "@/lib/api";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import { getConversation } from "@/lib/api/messages";
import type { ConversationResult, Message } from "@/lib/api/messages";

/* -------------------------------------------------------------------------- */
/*  Revalidation                                                              */
/* -------------------------------------------------------------------------- */

function revalidate(path: string): void {
  revalidatePath(path, "page");
}

const OWNER_MESSAGES = "/[locale]/(dashboard)/owner/messages";
const OWNER_NOTIFICATIONS = "/[locale]/(dashboard)/owner/notifications";
const FREELANCER_NOTIFICATIONS = "/[locale]/(dashboard)/freelancer/notifications";

/* -------------------------------------------------------------------------- */
/*  Notifications                                                              */
/* -------------------------------------------------------------------------- */

/**
 * Mark notifications as read. Pass `"all"` to clear every unread notification,
 * or an array of ids to clear a specific subset.
 */
export async function markNotificationsRead(
  target: "all" | string[],
): Promise<ActionResult> {
  const body = target === "all" ? { all: true } : { ids: target };
  const result = await authedMutate("/notifications/read", {
    method: "PUT",
    body,
  });
  if (result.ok) {
    revalidate(OWNER_NOTIFICATIONS);
    revalidate(FREELANCER_NOTIFICATIONS);
  }
  return result;
}

export async function deleteNotification(id: string): Promise<ActionResult> {
  const result = await authedMutate(`/notifications/${id}`, {
    method: "DELETE",
  });
  if (result.ok) {
    revalidate(OWNER_NOTIFICATIONS);
    revalidate(FREELANCER_NOTIFICATIONS);
  }
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Messaging (owner)                                                          */
/* -------------------------------------------------------------------------- */

/**
 * Load a full conversation for the client thread pane. Wraps the read in an
 * `ActionResult` so the client never sees a thrown error or the bearer token.
 */
export async function loadConversation(
  memberId: string,
): Promise<ActionResult<ConversationResult>> {
  try {
    const data = await getConversation(memberId);
    return { ok: true, data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { ok: false, status: error.status, message: error.message };
    }
    return {
      ok: false,
      status: 500,
      message: error instanceof Error ? error.message : "Request failed.",
    };
  }
}

/** Owner sends a direct message to one workspace member. */
export async function sendMessage(
  memberId: string,
  content: string,
): Promise<ActionResult<Message>> {
  const result = await authedMutate<Message>("/workspace/messages", {
    method: "POST",
    body: { receiver_id: memberId, content },
  });
  if (result.ok) revalidate(OWNER_MESSAGES);
  return result;
}

/** Owner broadcasts a single message to every active workspace member. */
export async function broadcastMessage(
  content: string,
): Promise<ActionResult<Message>> {
  const result = await authedMutate<Message>("/workspace/messages/broadcast", {
    method: "POST",
    body: { content },
  });
  if (result.ok) revalidate(OWNER_MESSAGES);
  return result;
}

/**
 * Owner marks every message received from `memberId` in the thread as read.
 * The backend resolves the workspace from the authenticated owner and expects
 * the counterpart under the `with` key.
 */
export async function markThreadRead(
  memberId: string,
): Promise<ActionResult> {
  const result = await authedMutate("/workspace/messages/read", {
    method: "PUT",
    body: { with: memberId },
  });
  if (result.ok) revalidate(OWNER_MESSAGES);
  return result;
}
