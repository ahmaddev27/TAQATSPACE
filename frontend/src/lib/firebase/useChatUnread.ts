"use client";

import { useEffect, useMemo, useState } from "react";
import { isFirebaseConfigured } from "./app";
import {
  signInForChat,
  watchConversations,
  type ChatConversation,
} from "./chat";
import { getSeenMap, subscribeSeen } from "./chatRead";

/**
 * Live count of conversations with an unseen incoming last message — the source
 * for the sidebar Chat badge. Signs in to Firebase (cached/idempotent) and
 * watches the user's conversations; read state is tracked locally (see
 * {@link getSeenMap}), so opening a thread clears its unread immediately.
 *
 * Returns 0 when Firebase is unconfigured or there is no signed-in user, so the
 * badge simply never appears in those cases.
 */
export function useChatUnread(uid: string | undefined): number {
  const [conversations, setConversations] = useState<ChatConversation[]>([]);
  // The local seen-map, mirrored into state so the count recomputes on change.
  // Lazy initializer reads localStorage once (returns {} during SSR).
  const [seen, setSeen] = useState<Record<string, number>>(() => getSeenMap());

  // Sign in once, then watch this user's conversations.
  useEffect(() => {
    if (!uid || !isFirebaseConfigured()) return;

    let unsub: (() => void) | null = null;
    let active = true;

    void (async () => {
      const session = await signInForChat();
      if (!active || !session) return;
      unsub = watchConversations(uid, setConversations);
    })();

    return () => {
      active = false;
      unsub?.();
    };
  }, [uid]);

  // Re-read the seen-map when a thread is marked seen (here or in another tab).
  useEffect(() => subscribeSeen(() => setSeen(getSeenMap())), []);

  return useMemo(() => {
    if (!uid) return 0;

    return conversations.reduce((count, conv) => {
      const incoming =
        conv.lastSenderId !== null && conv.lastSenderId !== uid;
      const unseen = (conv.updatedAt ?? 0) > (seen[conv.id] ?? 0);
      return count + (incoming && unseen ? 1 : 0);
    }, 0);
  }, [uid, conversations, seen]);
}
