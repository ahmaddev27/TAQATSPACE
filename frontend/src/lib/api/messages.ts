import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";

/* -------------------------------------------------------------------------- */
/*  Types                                                                      */
/* -------------------------------------------------------------------------- */

export type MessageType = "direct" | "broadcast";

/** Counterpart shown alongside a thread (owner inbox + opened conversation). */
export interface MessageMember {
  id: string;
  name: string;
  avatar: string | null;
  specialty?: string | null;
}

/** Compact last-message used in the thread list. */
export interface ThreadLastMessage {
  id: string;
  sender_id: string;
  content: string;
  read_at: string | null;
  created_at: string | null;
}

/** One conversation summary in the owner inbox. */
export interface MessageThread {
  member: MessageMember;
  last_message: ThreadLastMessage | null;
  unread_count: number;
}

/** A single message inside an opened conversation. */
export interface Message {
  id: string;
  sender_id: string;
  receiver_id: string | null;
  workspace_id: string;
  type: MessageType;
  content: string;
  read_at: string | null;
  is_read: boolean;
  created_at: string | null;
  sender?: {
    id: string;
    name: string;
    avatar: string | null;
  };
}

/** `GET /workspace/messages` payload. */
export interface ThreadListResult {
  threads: MessageThread[];
}

/** `GET /workspace/messages/{member}` payload (messages oldest-first). */
export interface ConversationResult {
  member: MessageMember;
  messages: Message[];
}

/* -------------------------------------------------------------------------- */
/*  Reads                                                                      */
/* -------------------------------------------------------------------------- */

/** Owner inbox: one thread per member, with the last message and unread count. */
export async function listThreads(): Promise<MessageThread[]> {
  const res = await serverFetch<ApiEnvelope<ThreadListResult>>(
    "/workspace/messages",
  );
  return res.data.threads;
}

/** Full direct conversation between the owner and one member (oldest first). */
export async function getConversation(
  memberId: string,
): Promise<ConversationResult> {
  const res = await serverFetch<ApiEnvelope<ConversationResult>>(
    `/workspace/messages/${memberId}`,
  );
  return res.data;
}
