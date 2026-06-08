"use server";

import { serverFetch } from "@/lib/api";
import { authedMutate, type ActionResult } from "./client";

/** Metadata for a stored chat attachment; embedded in the Firestore message. */
export interface ChatAttachmentMeta {
  path: string;
  name: string;
  type: string;
  size: number;
}

/**
 * Upload a chat attachment to S3 via the backend (multipart). The bearer lives
 * in an httpOnly cookie, so the upload goes server-side through `authedMutate`.
 * Returns the stored file's metadata for the client to attach to its message.
 */
export async function uploadChatAttachmentAction(
  formData: FormData,
): Promise<ActionResult<ChatAttachmentMeta>> {
  return authedMutate<ChatAttachmentMeta>("/chat/attachments", {
    method: "POST",
    formData,
  });
}

/**
 * Resolve a fresh viewable URL for a stored attachment path — a short-lived
 * signed S3 URL — so older messages stay accessible without persisting an
 * expiring URL in Firestore. Returns null on any failure.
 */
export async function resolveChatAttachmentUrlAction(
  path: string,
): Promise<string | null> {
  try {
    const res = await serverFetch<{ data: { url: string } }>(
      `/chat/attachments/url?path=${encodeURIComponent(path)}`,
    );
    return res.data?.url ?? null;
  } catch {
    return null;
  }
}
