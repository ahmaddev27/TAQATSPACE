import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";

/**
 * A chat-able contact for the authenticated user, scoped to their role by the
 * backend (owner → members, freelancer → workspace owner[s]).
 */
export interface ChatContact {
  id: string;
  name: string;
  /** Resolved avatar URL (presigned on S3), or null → render the name initial. */
  avatar: string | null;
  workspace_id: string;
  /** The contact's role (owner/freelancer/admin), for the by-type filter. */
  role?: string;
  /** True when the viewer is a member of this contact's workspace (badge). */
  subscribed?: boolean;
}

/**
 * Owner/freelancer: the user's chat-able contacts (`GET /chat/contacts`).
 * Server-only — relies on the bearer cookie via `serverFetch`.
 */
export async function listChatContacts(): Promise<ChatContact[]> {
  const res = await serverFetch<ApiEnvelope<{ contacts: ChatContact[] }>>(
    "/chat/contacts",
  );
  return res.data.contacts;
}
