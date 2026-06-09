"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Icon } from "@/components/ui/Icon";
import { useToast } from "@/components/providers/ToastProvider";
import { avatarInitial } from "@/components/features/owner/format";
import { isFirebaseConfigured } from "@/lib/firebase/app";
import {
  conversationId,
  sendMessage,
  signInForChat,
  watchConversations,
  watchMessages,
  type ChatConversation,
  type ChatMessage,
  type ChatParticipant,
} from "@/lib/firebase/chat";
import { markConversationSeen } from "@/lib/firebase/chatRead";
import type { ChatContact } from "@/lib/api/chat";
import type { ChatAttachmentMeta } from "@/lib/actions/chat";
import { ChatThread } from "./ChatThread";
import { ChatComposer } from "./ChatComposer";
import { relativeTime } from "./time";
import "./chat.css";

export interface ChatScreenProps {
  /** The signed-in user (= the chat uid and message author). */
  self: ChatParticipant;
  /** Role-scoped contacts the user may start a conversation with. */
  contacts: ChatContact[];
}

/** Lifecycle of the Firebase auth bridge. */
type AuthState = "loading" | "ready" | "unavailable";

/**
 * Shared realtime chat surface for owners and freelancers: a left pane listing
 * live conversations + chat-able contacts, and a right pane with the selected
 * thread and a composer. All Firestore access is env-gated; when Firebase is
 * unconfigured the component renders a tidy "unavailable" state.
 */
export function ChatScreen({ self, contacts }: ChatScreenProps) {
  const t = useTranslations("chat");
  const locale = useLocale();
  const { toast } = useToast();

  const [authState, setAuthState] = useState<AuthState>(
    isFirebaseConfigured() ? "loading" : "unavailable",
  );
  const [conversations, setConversations] = useState<ChatConversation[]>([]);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [activeContactId, setActiveContactId] = useState<string | null>(null);
  // Free-text filter over the left-pane list (contacts + live conversations).
  const [query, setQuery] = useState("");
  // Optional by-type filter (e.g. admin filtering owners vs freelancers). null = all.
  const [roleFilter, setRoleFilter] = useState<string | null>(null);
  // Id of the conversation whose first snapshot has arrived; used to derive the
  // thread loading state without a synchronous setState inside the effect.
  const [loadedConvId, setLoadedConvId] = useState<string | null>(null);

  // Contacts indexed by id for quick name/workspace lookup.
  const contactById = useMemo(() => {
    const map = new Map<string, ChatContact>();
    for (const c of contacts) map.set(c.id, c);
    return map;
  }, [contacts]);

  // ---- Auth bridge: sign in to Firebase once on mount. ----
  useEffect(() => {
    if (!isFirebaseConfigured()) return;
    let active = true;
    (async () => {
      const session = await signInForChat();
      if (!active) return;
      setAuthState(session ? "ready" : "unavailable");
    })();
    return () => {
      active = false;
    };
  }, []);

  // ---- Watch the user's conversations once authenticated. ----
  useEffect(() => {
    if (authState !== "ready") return;
    const unsub = watchConversations(self.id, setConversations);
    return unsub;
  }, [authState, self.id]);

  // ---- Watch messages for the active conversation. ----
  const activeConvId = activeContactId
    ? conversationId(self.id, activeContactId)
    : null;

  useEffect(() => {
    if (authState !== "ready" || !activeConvId) return;

    const unsub = watchMessages(activeConvId, (list) => {
      setMessages(list);
      setLoadedConvId(activeConvId);
    });

    // Clear the previous thread's messages when the selection changes or the
    // pane closes — done in cleanup to avoid a synchronous setState in render.
    return () => {
      unsub();
      setMessages([]);
      setLoadedConvId(null);
    };
  }, [authState, activeConvId]);

  // The thread is "loading" while a conversation is selected but its first
  // Firestore snapshot hasn't resolved yet.
  const loadingThread = activeConvId != null && loadedConvId !== activeConvId;

  // Viewing the active conversation (or receiving a new message while it's open)
  // marks it seen, which clears its unread badge in the sidebar.
  useEffect(() => {
    if (!activeConvId) return;
    const conv = conversations.find((c) => c.id === activeConvId);
    if (conv) markConversationSeen(conv.id, conv.updatedAt);
  }, [activeConvId, conversations]);

  // ---- Build the left-pane list: live conversations first, then contacts
  //      that have no conversation yet (so a new chat can be started). ----
  const knownContactIds = useMemo(() => {
    const ids = new Set<string>();
    for (const conv of conversations) {
      for (const p of conv.participants) {
        if (p !== self.id) ids.add(p);
      }
    }
    return ids;
  }, [conversations, self.id]);

  const newContacts = useMemo(
    () => contacts.filter((c) => !knownContactIds.has(c.id)),
    [contacts, knownContactIds],
  );

  /** Resolve the other participant's id + display name for a conversation. */
  const otherParticipant = useCallback(
    (conv: ChatConversation): { id: string; name: string } => {
      const id = conv.participants.find((p) => p !== self.id) ?? "";
      const name =
        conv.participantNames[id] ??
        contactById.get(id)?.name ??
        t("unknownContact");
      return { id, name };
    },
    [self.id, contactById, t],
  );

  // ---- The distinct contact roles present, for the optional by-type filter.
  //      The filter only surfaces when the list spans more than one role
  //      (i.e. admins and freelancers, not single-role owners). ----
  const availableRoles = useMemo(() => {
    const set = new Set<string>();
    for (const c of contacts) if (c.role) set.add(c.role);
    return [...set];
  }, [contacts]);

  const roleLabel = useCallback(
    (role: string): string =>
      role === "workspace_owner"
        ? t("roleOwner")
        : role === "freelancer"
          ? t("roleFreelancer")
          : role === "admin"
            ? t("roleAdmin")
            : role,
    [t],
  );

  // ---- Client-side search + by-type filter over the left pane (live
  //      conversations + contacts). Search matches the display name; the role
  //      filter matches the contact's role (resolved via `contactById` for
  //      conversations). ----
  const normalizedQuery = query.trim().toLowerCase();
  const matchesQuery = useCallback(
    (name: string) =>
      normalizedQuery === "" || name.toLowerCase().includes(normalizedQuery),
    [normalizedQuery],
  );
  const visibleConversations = useMemo(
    () =>
      conversations.filter((conv) => {
        const other = otherParticipant(conv);
        return (
          matchesQuery(other.name) &&
          (!roleFilter || contactById.get(other.id)?.role === roleFilter)
        );
      }),
    [conversations, matchesQuery, roleFilter, otherParticipant, contactById],
  );
  const visibleNewContacts = useMemo(
    () =>
      newContacts.filter(
        (c) => matchesQuery(c.name) && (!roleFilter || c.role === roleFilter),
      ),
    [newContacts, matchesQuery, roleFilter],
  );

  const activeContact = activeContactId
    ? contactById.get(activeContactId)
    : null;
  const activeName =
    activeContact?.name ??
    conversations
      .map(otherParticipant)
      .find((p) => p.id === activeContactId)?.name ??
    t("unknownContact");

  const handleSend = useCallback(
    async (
      text: string,
      attachment: ChatAttachmentMeta | null,
    ): Promise<boolean> => {
      if (!activeContactId || !activeConvId) return false;
      const contactName = activeName;
      const workspaceId = contactById.get(activeContactId)?.workspace_id ?? null;
      const participants: ChatParticipant[] = [
        self,
        { id: activeContactId, name: contactName },
      ];
      const ok = await sendMessage(
        activeConvId,
        participants,
        text,
        self,
        workspaceId,
        attachment,
      );
      if (!ok) {
        toast({ tone: "err", title: t("sendFailed") });
      }
      return ok;
    },
    [activeContactId, activeConvId, activeName, contactById, self, toast, t],
  );

  if (authState === "unavailable") {
    return <ChatUnavailable />;
  }

  if (authState === "loading") {
    return (
      <div className="chat-unavailable">
        <div className="chat-unavailable-card">
          <span className="btn-spinner" aria-hidden="true" />
          <div className="muted">{t("connecting")}</div>
        </div>
      </div>
    );
  }

  return (
    <div className={`chat-shell ${activeContactId ? "has-active" : ""}`.trim()}>
      <aside className="chat-aside">
        <div className="chat-aside-head">
          <div className="h3">{t("title")}</div>
        </div>
        {(conversations.length > 0 || contacts.length > 0) && (
          <div className="chat-search">
            <Icon name="search" size={16} />
            <input
              type="search"
              className="chat-search-input"
              placeholder={t("searchPlaceholder")}
              aria-label={t("searchPlaceholder")}
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
          </div>
        )}
        {availableRoles.length > 1 && (
          <div className="chat-filter">
            <select
              className="chat-filter-select"
              value={roleFilter ?? ""}
              onChange={(e) => setRoleFilter(e.target.value || null)}
              aria-label={t("filterByType")}
            >
              <option value="">{t("filterAll")}</option>
              {availableRoles.map((role) => (
                <option key={role} value={role}>
                  {roleLabel(role)}
                </option>
              ))}
            </select>
          </div>
        )}
        <div className="chat-aside-scroll">
          {conversations.length === 0 && newContacts.length === 0 ? (
            <div className="chat-empty" style={{ padding: 28 }}>
              <div>
                <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                  {t("noContactsTitle")}
                </div>
                <div style={{ fontSize: "var(--fs-sm)" }}>
                  {t("noContactsBody")}
                </div>
              </div>
            </div>
          ) : visibleConversations.length === 0 &&
            visibleNewContacts.length === 0 ? (
            <div className="chat-empty" style={{ padding: 28 }}>
              <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                {t("noResults")}
              </div>
            </div>
          ) : (
            <>
              {visibleConversations.map((conv) => {
                const other = otherParticipant(conv);
                return (
                  <ContactRow
                    key={conv.id}
                    name={other.name}
                    avatar={contactById.get(other.id)?.avatar ?? null}
                    preview={conv.lastMessage || t("noMessagesYet")}
                    time={relativeTime(conv.updatedAt, locale)}
                    active={other.id === activeContactId}
                    onSelect={() => setActiveContactId(other.id)}
                  />
                );
              })}

              {visibleNewContacts.length > 0 && (
                <>
                  <div className="chat-section-label">{t("startNew")}</div>
                  {visibleNewContacts.map((c) => (
                    <ContactRow
                      key={c.id}
                      name={c.name}
                      avatar={c.avatar}
                      preview={t("tapToStart")}
                      active={c.id === activeContactId}
                      onSelect={() => setActiveContactId(c.id)}
                    />
                  ))}
                </>
              )}
            </>
          )}
        </div>
      </aside>

      <section className="chat-main">
        {activeContactId ? (
          <>
            <ChatThread
              contactName={activeName}
              contactAvatar={activeContact?.avatar ?? null}
              selfName={self.name}
              selfAvatar={self.avatar ?? null}
              messages={messages}
              selfUid={self.id}
              loading={loadingThread}
              onBack={() => setActiveContactId(null)}
            />
            <ChatComposer onSend={handleSend} />
          </>
        ) : (
          <div className="chat-empty">
            <div>
              <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                {t("selectTitle")}
              </div>
              <div style={{ fontSize: "var(--fs-sm)" }}>{t("selectBody")}</div>
            </div>
          </div>
        )}
      </section>
    </div>
  );
}

/** A single selectable row in the left pane (conversation or new contact). */
function ContactRow({
  name,
  avatar,
  preview,
  time,
  active,
  onSelect,
}: {
  name: string;
  avatar?: string | null;
  preview: string;
  time?: string;
  active: boolean;
  onSelect: () => void;
}) {
  return (
    <button
      type="button"
      className={`chat-item ${active ? "active" : ""}`.trim()}
      onClick={onSelect}
    >
      <Avatar initial={avatarInitial(name)} src={avatar} alt={name} round />
      <div className="grow">
        <div className="chat-name">{name}</div>
        <div className="chat-preview">{preview}</div>
      </div>
      {time ? <span className="chat-time">{time}</span> : null}
    </button>
  );
}

/** The clean "chat not available yet" empty state (Firebase unconfigured). */
function ChatUnavailable() {
  const t = useTranslations("chat");
  return (
    <div className="chat-unavailable">
      <div className="chat-unavailable-card">
        <span className="chat-unavailable-icon">
          <Icon name="chat" size={26} />
        </span>
        <div className="h3">{t("unavailableTitle")}</div>
        <p className="muted">{t("unavailableBody")}</p>
      </div>
    </div>
  );
}
