"use client";

import { useEffect, useRef } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { useToast } from "@/components/providers/ToastProvider";
import { isFirebaseConfigured } from "@/lib/firebase/app";
import {
  signInForChat,
  watchConversations,
  type ChatConversation,
} from "@/lib/firebase/chat";
import { getSeenMap } from "@/lib/firebase/chatRead";
import { getActiveConversation } from "@/lib/firebase/chatActive";

/** The chat surface route for each role, or null when the role has no chat. */
function chatPathForRole(role: string | undefined): string | null {
  switch (role) {
    case "workspace_owner":
      return "/owner/chat";
    case "freelancer":
      return "/freelancer/chat";
    case "admin":
      return "/admin/chat";
    default:
      return null;
  }
}

/**
 * Site-wide "new message" popup (Messenger-style). Renders nothing.
 *
 * Mounted in the dashboard shell for every authenticated user. Watches the
 * user's Firestore conversations and, when an incoming message arrives that is
 * newer than what we've already shown and that the user hasn't read, raises a
 * clickable toast (sender + preview) that opens the conversation.
 *
 * Guards against noise:
 *  - First snapshot only sets a baseline — no popups for backlog on load.
 *  - The conversation currently open on the chat screen is skipped (you're
 *    already reading it); see {@link getActiveConversation}.
 *  - Already-seen conversations are skipped via the local seen-map.
 *  - No-op entirely when Firebase is unconfigured or no user is signed in.
 */
export function ChatPopupListener() {
  const { user } = useAuth();
  const { toast } = useToast();
  const router = useRouter();
  const t = useTranslations("chat");

  const uid = user?.id;
  const role = user?.role;

  // Keep the latest closures without re-subscribing the Firestore listener.
  const toastRef = useRef(toast);
  const routerRef = useRef(router);
  const tRef = useRef(t);
  useEffect(() => {
    toastRef.current = toast;
    routerRef.current = router;
    tRef.current = t;
  }, [toast, router, t]);

  // Highest `updatedAt` we've already accounted for, per conversation, so each
  // new message pops exactly once. Null until the first snapshot sets a baseline.
  const baselineRef = useRef<Record<string, number> | null>(null);

  useEffect(() => {
    if (!uid || !isFirebaseConfigured()) return;

    const chatPath = chatPathForRole(role);
    baselineRef.current = null;
    let unsub: (() => void) | null = null;
    let active = true;

    void (async () => {
      const session = await signInForChat();
      if (!active || !session) return;

      unsub = watchConversations(uid, (conversations: ChatConversation[]) => {
        // First snapshot: record where every conversation stands, pop nothing.
        if (baselineRef.current === null) {
          baselineRef.current = Object.fromEntries(
            conversations.map((c) => [c.id, c.updatedAt ?? 0]),
          );
          return;
        }

        const baseline = baselineRef.current;
        const seen = getSeenMap();
        const activeConv = getActiveConversation();

        for (const conv of conversations) {
          const ts = conv.updatedAt ?? 0;
          const isNew = ts > (baseline[conv.id] ?? 0);
          if (!isNew) continue;

          // Account for it now so it never double-pops on a later snapshot.
          baseline[conv.id] = ts;

          const incoming =
            conv.lastSenderId !== null && conv.lastSenderId !== uid;
          const unseen = ts > (seen[conv.id] ?? 0);
          const onScreen = activeConv === conv.id;

          if (!incoming || !unseen || onScreen) continue;

          const senderName = conv.lastSenderId
            ? conv.participantNames[conv.lastSenderId]
            : null;
          const contactId = conv.participants.find((p) => p !== uid) ?? null;

          toastRef.current({
            initial: (senderName ?? "?").charAt(0).toUpperCase(),
            title: senderName ?? tRef.current("popupTitle"),
            body: conv.lastMessage || tRef.current("popupBody"),
            onClick:
              chatPath && contactId
                ? () => routerRef.current.push(`${chatPath}?c=${contactId}`)
                : undefined,
          });
        }
      });
    })();

    return () => {
      active = false;
      unsub?.();
    };
  }, [uid, role]);

  return null;
}
