"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { Avatar } from "@/components/ui/Avatar";
import { Link } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { avatarInitial } from "@/components/features/owner/format";
import { isFirebaseConfigured } from "@/lib/firebase/app";
import {
  sendMessage,
  signInForChat,
  watchConversations,
  watchMessages,
  type ChatConversation,
  type ChatMessage,
  type ChatParticipant,
} from "@/lib/firebase/chat";
import {
  getSeenMap,
  markConversationSeen,
  subscribeSeen,
} from "@/lib/firebase/chatRead";
import type { ChatAttachmentMeta } from "@/lib/actions/chat";
import { ChatThread } from "./ChatThread";
import { ChatComposer } from "./ChatComposer";
import { relativeTime } from "./time";
// The widget reuses the chat list/thread/composer classes — load their styles
// (chat.css is otherwise only imported by the full ChatScreen) so the panel is
// styled and usable on any page, not just /chat.
import "./chat.css";
import "./chat-widget.css";

/** Full chat surface route for the current role (for the "open all" link). */
function chatPathForRole(role: string | undefined): string {
  if (role === "workspace_owner") return "/owner/chat";
  if (role === "admin") return "/admin/chat";
  return "/freelancer/chat";
}

/**
 * Site-wide floating chat launcher. A bubble with an unread badge sits in the
 * corner of every authenticated page; clicking it opens a compact panel with the
 * user's conversations and an inline thread + composer (reusing the full chat's
 * pieces). No-op until Firebase is configured.
 */
export function ChatWidget() {
  const t = useTranslations("chat");
  const locale = useLocale();
  const { user } = useAuth();
  const uid = user?.id;

  const [open, setOpen] = useState(false);
  const [authed, setAuthed] = useState(false);
  const [conversations, setConversations] = useState<ChatConversation[]>([]);
  const [activeConvId, setActiveConvId] = useState<string | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [seen, setSeen] = useState<Record<string, number>>(() => getSeenMap());

  const self: ChatParticipant | null = useMemo(
    () =>
      user
        ? { id: user.id, name: user.name, avatar: user.avatar ?? null }
        : null,
    [user],
  );

  // Sign in once, then keep the conversation list live (also feeds the badge).
  useEffect(() => {
    if (!uid || !isFirebaseConfigured()) return;
    let unsub: (() => void) | null = null;
    let active = true;
    void (async () => {
      const session = await signInForChat();
      if (!active || !session) return;
      setAuthed(true);
      unsub = watchConversations(uid, setConversations);
    })();
    return () => {
      active = false;
      unsub?.();
    };
  }, [uid]);

  useEffect(() => subscribeSeen(() => setSeen(getSeenMap())), []);

  // Watch the open thread's messages.
  useEffect(() => {
    if (!authed || !activeConvId) return;
    const unsub = watchMessages(activeConvId, setMessages);
    return () => {
      unsub();
      setMessages([]);
    };
  }, [authed, activeConvId]);

  // Mark the open conversation seen (also as new messages arrive while open).
  useEffect(() => {
    if (!activeConvId) return;
    const conv = conversations.find((c) => c.id === activeConvId);
    if (conv) markConversationSeen(conv.id, conv.updatedAt);
  }, [activeConvId, conversations, messages.length]);

  const isUnread = useCallback(
    (conv: ChatConversation): boolean => {
      const incoming = conv.lastSenderId !== null && conv.lastSenderId !== uid;
      return incoming && (conv.updatedAt ?? 0) > (seen[conv.id] ?? 0);
    },
    [uid, seen],
  );

  const unreadCount = useMemo(
    () => conversations.reduce((n, c) => n + (isUnread(c) ? 1 : 0), 0),
    [conversations, isUnread],
  );

  const activeConv = activeConvId
    ? conversations.find((c) => c.id === activeConvId) ?? null
    : null;

  const otherOf = useCallback(
    (conv: ChatConversation): { id: string; name: string } => {
      const id = conv.participants.find((p) => p !== uid) ?? "";
      return { id, name: conv.participantNames[id] ?? t("unknownContact") };
    },
    [uid, t],
  );

  const handleSend = useCallback(
    async (
      text: string,
      attachment: ChatAttachmentMeta | null,
    ): Promise<boolean> => {
      if (!self || !activeConv) return false;
      const other = otherOf(activeConv);
      const ok = await sendMessage(
        activeConv.id,
        [self, { id: other.id, name: other.name }],
        text,
        self,
        activeConv.workspaceId,
        attachment,
      );
      if (ok) {
        const preview = text.trim() || attachment?.name || "";
        void fetch("/api/chat/notify", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "include",
          body: JSON.stringify({ recipient_id: other.id, preview }),
        }).catch(() => {});
      }
      return ok;
    },
    [self, activeConv, otherOf],
  );

  // Render nothing until chat is available for a signed-in user.
  if (!uid || !self || !isFirebaseConfigured()) return null;

  return (
    <>
      {open && (
        <div className="chat-widget" role="dialog" aria-label={t("title")}>
          <div className="chat-widget-head">
            {activeConv ? (
              <button
                type="button"
                className="chat-widget-icon-btn"
                onClick={() => setActiveConvId(null)}
                aria-label={t("back")}
              >
                <Icon name="arrowR" size={18} />
              </button>
            ) : (
              <span className="chat-widget-title">{t("title")}</span>
            )}
            {activeConv && (
              <span className="chat-widget-title chat-widget-title--thread">
                {otherOf(activeConv).name}
              </span>
            )}
            <span className="chat-widget-head-actions">
              <Link
                href={chatPathForRole(user?.role)}
                className="chat-widget-icon-btn"
                aria-label={t("openFull")}
                title={t("openFull")}
                onClick={() => setOpen(false)}
              >
                <Icon name="grid" size={16} />
              </Link>
              <button
                type="button"
                className="chat-widget-icon-btn"
                onClick={() => setOpen(false)}
                aria-label={t("back")}
              >
                <Icon name="x" size={18} />
              </button>
            </span>
          </div>

          <div className="chat-widget-body">
            {activeConv ? (
              <>
                <ChatThread
                  contactName={otherOf(activeConv).name}
                  contactAvatar={null}
                  selfName={self.name}
                  selfAvatar={self.avatar ?? null}
                  messages={messages}
                  selfUid={self.id}
                  loading={false}
                  onBack={() => setActiveConvId(null)}
                />
                <ChatComposer onSend={handleSend} />
              </>
            ) : conversations.length === 0 ? (
              <div className="chat-widget-empty">
                <Icon name="chat" size={26} />
                <p className="muted">{t("noContactsTitle")}</p>
              </div>
            ) : (
              <div className="chat-widget-list">
                {conversations.map((conv) => {
                  const other = otherOf(conv);
                  return (
                    <button
                      key={conv.id}
                      type="button"
                      className={`chat-item ${isUnread(conv) ? "is-unread" : ""}`.trim()}
                      onClick={() => setActiveConvId(conv.id)}
                    >
                      <Avatar initial={avatarInitial(other.name)} round />
                      <div className="grow" style={{ minWidth: 0 }}>
                        <div className="chat-name">
                          <span>{other.name}</span>
                        </div>
                        <div className="chat-preview">
                          {conv.lastMessage || t("noMessagesYet")}
                        </div>
                      </div>
                      <span className="chat-row-meta">
                        <span className="chat-time">
                          {relativeTime(conv.updatedAt, locale)}
                        </span>
                        {isUnread(conv) && (
                          <span className="chat-unread-dot" aria-hidden="true" />
                        )}
                      </span>
                    </button>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      )}

      <button
        type="button"
        className={`chat-fab ${open ? "is-open" : ""}`.trim()}
        onClick={() => setOpen((v) => !v)}
        aria-label={t("title")}
      >
        <Icon name={open ? "x" : "chat"} size={24} />
        {!open && unreadCount > 0 && (
          <span className="chat-fab-badge">{unreadCount > 9 ? "9+" : unreadCount}</span>
        )}
      </button>
    </>
  );
}
