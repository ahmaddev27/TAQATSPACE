import { setRequestLocale } from "next-intl/server";
import { serverFetch } from "@/lib/api";
import { listChatContacts, type ChatContact } from "@/lib/api/chat";
import { ChatScreen } from "@/components/features/chat/ChatScreen";
import type { ApiEnvelope, User } from "@/lib/types";

export const dynamic = "force-dynamic";

/**
 * Owner realtime chat (Firestore-backed). The owner chats with their workspace
 * members. Contacts and the signed-in identity load server-side; the live
 * thread + auth bridge run client-side in {@link ChatScreen}. Contacts may be
 * empty if the chat backend is unconfigured — the screen still degrades to the
 * "unavailable" state.
 */
export default async function OwnerChatPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ c?: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const me = await serverFetch<ApiEnvelope<{ user: User }>>("/auth/me");
  const contacts = await safeContacts();
  const { c } = await searchParams;

  return (
    <div className="page">
      <ChatScreen
        self={{
          id: me.data.user.id,
          name: me.data.user.name,
          avatar: me.data.user.avatar,
        }}
        contacts={contacts}
        initialContactId={c ?? null}
      />
    </div>
  );
}

/**
 * Fetch contacts, tolerating a 503 (Firebase unconfigured) or any backend
 * hiccup — the chat screen handles an empty contact list gracefully.
 */
async function safeContacts(): Promise<ChatContact[]> {
  try {
    return await listChatContacts();
  } catch {
    return [];
  }
}
