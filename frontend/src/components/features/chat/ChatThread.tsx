"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Icon } from "@/components/ui/Icon";
import { avatarInitial } from "@/components/features/owner/format";
import { resolveChatAttachmentUrlAction } from "@/lib/actions/chat";
import type { ChatAttachment, ChatMessage } from "@/lib/firebase/chat";
import { clockTime } from "./time";

export interface ChatThreadProps {
  contactName: string;
  /** The contact's resolved avatar URL (or null → render their name initial). */
  contactAvatar?: string | null;
  /** The signed-in user's display name + avatar, for sided message avatars. */
  selfName: string;
  selfAvatar?: string | null;
  messages: ChatMessage[];
  /** The signed-in Firebase uid (= our user id), to side each bubble. */
  selfUid: string;
  loading: boolean;
  onBack: () => void;
}

/**
 * Right pane: a contact header and the live message stream, with a toggle to a
 * gallery of every file shared in the conversation. Auto-scrolls to the newest
 * message whenever the stream grows.
 */
export function ChatThread({
  contactName,
  contactAvatar,
  selfName,
  selfAvatar,
  messages,
  selfUid,
  loading,
  onBack,
}: ChatThreadProps) {
  const t = useTranslations("chat");
  const locale = useLocale();
  const endRef = useRef<HTMLDivElement>(null);
  const [showAttachments, setShowAttachments] = useState(false);

  // Every attachment shared in this conversation, newest first — a gallery the
  // user can revisit any time. Derived from the already-loaded message stream.
  const attachments = useMemo(() => {
    const list: ChatAttachment[] = [];
    for (const m of messages) if (m.attachment) list.push(m.attachment);
    return list.reverse();
  }, [messages]);

  useEffect(() => {
    if (!showAttachments) endRef.current?.scrollIntoView({ block: "end" });
  }, [messages.length, showAttachments]);

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
        <Avatar
          initial={avatarInitial(contactName)}
          src={contactAvatar}
          alt={contactName}
          round
        />
        <div className="grow" style={{ minWidth: 0 }}>
          <div className="chat-name">{contactName}</div>
        </div>
        <button
          type="button"
          className={`chat-head-btn ${showAttachments ? "active" : ""}`.trim()}
          onClick={() => setShowAttachments((v) => !v)}
          aria-label={t("attachmentsPanel")}
          title={t("attachmentsPanel")}
          aria-pressed={showAttachments}
        >
          <Icon name="layers" />
          {attachments.length > 0 && (
            <span className="chat-head-badge">{attachments.length}</span>
          )}
        </button>
      </div>

      {showAttachments ? (
        <AttachmentsGallery attachments={attachments} />
      ) : (
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
            messages.map((m, i) => {
              const out = m.senderId === selfUid;
              // Show the sender's avatar only on the last message of a
              // consecutive same-sender run, so a burst of messages from one
              // person isn't a column of repeated faces. Other rows keep a
              // same-width spacer so every bubble stays aligned.
              const endsGroup =
                i === messages.length - 1 ||
                messages[i + 1].senderId !== m.senderId;
              const avatarSrc = out ? selfAvatar : contactAvatar;
              const avatarName = out ? selfName : contactName;
              return (
                <div key={m.id} className={`chat-row ${out ? "out" : "in"}`}>
                  {endsGroup ? (
                    <Avatar
                      initial={avatarInitial(avatarName)}
                      src={avatarSrc}
                      alt={avatarName}
                      size="sm"
                      round
                    />
                  ) : (
                    <span className="chat-msg-spacer" aria-hidden="true" />
                  )}
                  <div>
                    {m.attachment && (
                      <MessageAttachment attachment={m.attachment} />
                    )}
                    {m.text && <div className="chat-bubble">{m.text}</div>}
                    <div className="chat-stamp">
                      {clockTime(m.createdAt, locale)}
                    </div>
                  </div>
                </div>
              );
            })
          )}
          <div ref={endRef} />
        </div>
      )}
    </>
  );
}

/**
 * Resolve a fresh viewable URL for a stored attachment path — a short-lived
 * signed S3 URL — so links never go stale. Null until resolved.
 */
function useAttachmentUrl(path: string): string | null {
  const [url, setUrl] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    void resolveChatAttachmentUrlAction(path).then((resolved) => {
      if (active) setUrl(resolved);
    });
    return () => {
      active = false;
    };
  }, [path]);

  return url;
}

/**
 * Inline attachment inside a message bubble: images preview and open full-size
 * in a new tab; other files show a download chip.
 */
function MessageAttachment({ attachment }: { attachment: ChatAttachment }) {
  const url = useAttachmentUrl(attachment.path);
  const isImage = attachment.type.startsWith("image/");

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

/**
 * The conversation's full attachment history — a revisitable gallery of every
 * file shared, derived from the message stream so it always reflects the thread.
 */
function AttachmentsGallery({
  attachments,
}: {
  attachments: ChatAttachment[];
}) {
  const t = useTranslations("chat");

  if (attachments.length === 0) {
    return (
      <div className="chat-scroll">
        <div className="chat-empty">
          <div>
            <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
              {t("noAttachmentsTitle")}
            </div>
            <div style={{ fontSize: "var(--fs-sm)" }}>
              {t("noAttachmentsBody")}
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="chat-scroll">
      <div className="chat-gallery">
        {attachments.map((a, i) => (
          <AttachmentTile key={`${a.path}-${i}`} attachment={a} />
        ))}
      </div>
    </div>
  );
}

/** A single gallery tile: image thumbnail or file card; opens in a new tab. */
function AttachmentTile({ attachment }: { attachment: ChatAttachment }) {
  const url = useAttachmentUrl(attachment.path);
  const isImage = attachment.type.startsWith("image/");

  return (
    <a
      href={url ?? undefined}
      target="_blank"
      rel="noopener noreferrer"
      className={`chat-tile ${isImage ? "is-image" : "is-file"}`.trim()}
      aria-disabled={url ? undefined : true}
      title={attachment.name}
    >
      {isImage ? (
        url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={url} alt={attachment.name} loading="lazy" />
        ) : (
          <span className="btn-spinner" aria-hidden="true" />
        )
      ) : (
        <>
          <Icon name="doc" size={24} />
          <span className="chat-tile-name">{attachment.name}</span>
        </>
      )}
    </a>
  );
}
