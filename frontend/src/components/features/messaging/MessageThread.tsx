"use client";

import { useEffect, useRef } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Icon } from "@/components/ui/Icon";
import type { ConversationResult, Message } from "@/lib/api/messages";
import { avatarInitial } from "@/components/features/owner/format";
import { clockTime } from "./time";

export interface MessageThreadProps {
  conversation: ConversationResult;
  /** The signed-in owner's user id — determines bubble side. */
  ownerId: string;
  loading?: boolean;
  /** Shown on mobile to return to the thread list. */
  onBack: () => void;
}

/** Right pane: conversation header, scrollable bubbles, auto-scroll to latest. */
export function MessageThread({
  conversation,
  ownerId,
  loading,
  onBack,
}: MessageThreadProps) {
  const t = useTranslations("messaging.messages");
  const locale = useLocale();
  const scrollRef = useRef<HTMLDivElement>(null);
  const { member, messages } = conversation;

  // Keep the newest message in view as the thread loads / grows.
  useEffect(() => {
    const el = scrollRef.current;
    if (el) el.scrollTop = el.scrollHeight;
  }, [messages]);

  return (
    <div className="msg-thread" style={{ display: "contents" }}>
      <div className="msg-pane-head">
        <button
          type="button"
          className="icon-btn msg-pane-back"
          onClick={onBack}
          aria-label={t("title")}
        >
          <Icon name="arrowR" />
        </button>
        <Avatar initial={avatarInitial(member.name)} round />
        <div>
          <div style={{ fontWeight: 600 }}>{member.name}</div>
          {member.specialty && (
            <div className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
              {member.specialty}
            </div>
          )}
        </div>
      </div>

      <div className="msg-scroll" ref={scrollRef}>
        {loading ? (
          <div className="msg-empty">
            <span className="btn-spinner" aria-hidden="true" />
          </div>
        ) : messages.length === 0 ? (
          <div className="msg-empty">{t("noMessages")}</div>
        ) : (
          messages.map((m) => (
            <Bubble key={m.id} message={m} ownerId={ownerId} locale={locale} />
          ))
        )}
      </div>
    </div>
  );
}

interface BubbleProps {
  message: Message;
  ownerId: string;
  locale: string;
}

function Bubble({ message, ownerId, locale }: BubbleProps) {
  const t = useTranslations("messaging.messages");
  const out = message.sender_id === ownerId;
  const broadcast = message.type === "broadcast";

  return (
    <div className={`msg-row ${out ? "out" : "in"}`}>
      <div className={`msg-bubble ${broadcast ? "broadcast" : ""}`.trim()}>
        {broadcast && (
          <div className="msg-broadcast-tag">
            <Icon name="megaphone" size={12} />
            {t("broadcastTag")}
          </div>
        )}
        <div>{message.content}</div>
        <div className="msg-time">{clockTime(message.created_at, locale)}</div>
      </div>
    </div>
  );
}
