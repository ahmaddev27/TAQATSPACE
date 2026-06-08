"use client";

import { useEffect, useRef, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Icon } from "@/components/ui/Icon";
import { avatarInitial } from "@/components/features/owner/format";
import { resolveChatAttachmentUrlAction } from "@/lib/actions/chat";
import type { ChatAttachment, ChatMessage } from "@/lib/firebase/chat";
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
                  {m.attachment && (
                    <MessageAttachment attachment={m.attachment} />
                  )}
                  {m.text && <div className="chat-bubble">{m.text}</div>}
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

/**
 * Renders a message attachment. The file lives on S3; a fresh viewable URL is
 * resolved on demand from its path (so links never go stale). Images preview
 * inline and open full-size in a new tab; other files show a download chip.
 */
function MessageAttachment({ attachment }: { attachment: ChatAttachment }) {
  const t = useTranslations("chat");
  const [url, setUrl] = useState<string | null>(null);
  const isImage = attachment.type.startsWith("image/");

  useEffect(() => {
    let active = true;
    void resolveChatAttachmentUrlAction(attachment.path).then((resolved) => {
      if (active) setUrl(resolved);
    });
    return () => {
      active = false;
    };
  }, [attachment.path]);

  if (isImage) {
    return url ? (
      <a
        href={url}
        target="_blank"
        rel="noopener noreferrer"
        className="chat-attach-image"
      >
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={url} alt={attachment.name} loading="lazy" />
      </a>
    ) : (
      <div className="chat-attach-image chat-attach-loading">
        <span className="btn-spinner" aria-hidden="true" />
      </div>
    );
  }

  return (
    <a
      href={url ?? undefined}
      target="_blank"
      rel="noopener noreferrer"
      className="chat-attach-file"
      aria-disabled={url ? undefined : true}
      title={attachment.name}
    >
      <Icon name="doc" size={18} />
      <span className="chat-attach-fname">{attachment.name}</span>
      <Icon name={url ? "download" : "clock"} size={16} />
    </a>
  );
}
