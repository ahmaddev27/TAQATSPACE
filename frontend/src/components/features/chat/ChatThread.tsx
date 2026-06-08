"use client";

import { useEffect, useRef } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Icon } from "@/components/ui/Icon";
import { avatarInitial } from "@/components/features/owner/format";
import type { ChatMessage } from "@/lib/firebase/chat";
import { clockTime } from "./time";

export interface ChatThreadProps {
  contactName: string;
  messages: ChatMessage[];
  /** The signed-in Firebase uid (= our user id), to side each bubble. */
  selfUid: string;
  loading: boolean;
  onBack: () => void;
}

/**
 * Right pane: a contact header and the live message stream. Auto-scrolls to the
 * newest message whenever the list grows.
 */
export function ChatThread({
  contactName,
  messages,
  selfUid,
  loading,
  onBack,
}: ChatThreadProps) {
  const t = useTranslations("chat");
  const locale = useLocale();
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ block: "end" });
  }, [messages.length]);

  return (
    <>
      <div className="chat-main-head">
        <button
          type="button"
          className="chat-back"
          onClick={onBack}
          aria-label={t("back")}
        >
          <Icon name="arrowR" />
        </button>
        <Avatar initial={avatarInitial(contactName)} round />
        <div className="grow" style={{ minWidth: 0 }}>
          <div className="chat-name">{contactName}</div>
        </div>
      </div>

      <div className="chat-scroll">
        {loading && messages.length === 0 ? (
          <div className="chat-empty">{t("loading")}</div>
        ) : messages.length === 0 ? (
          <div className="chat-empty">
            <div>
              <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                {t("threadEmptyTitle")}
              </div>
              <div style={{ fontSize: "var(--fs-sm)" }}>
                {t("threadEmptyBody")}
              </div>
            </div>
          </div>
        ) : (
          messages.map((m) => {
            const out = m.senderId === selfUid;
            return (
              <div key={m.id} className={`chat-row ${out ? "out" : "in"}`}>
                <div>
                  <div className="chat-bubble">{m.text}</div>
                  <div className="chat-stamp">{clockTime(m.createdAt, locale)}</div>
                </div>
              </div>
            );
          })
        )}
        <div ref={endRef} />
      </div>
    </>
  );
}
