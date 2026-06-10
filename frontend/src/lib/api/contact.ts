import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";

/** A public contact-form submission, as shown in the admin inbox. */
export interface ContactMessage {
  id: string;
  name: string;
  email: string;
  subject: string;
  message: string;
  is_read: boolean;
  created_at: string | null;
}

export interface ContactMessagesResult {
  messages: ContactMessage[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    unread: number;
  };
}

/** Admin: the contact inbox (`GET /admin/contact-messages`). */
export async function listContactMessages(): Promise<ContactMessagesResult> {
  const res = await serverFetch<ApiEnvelope<ContactMessagesResult>>(
    "/admin/contact-messages?per_page=100",
  );
  return res.data;
}
