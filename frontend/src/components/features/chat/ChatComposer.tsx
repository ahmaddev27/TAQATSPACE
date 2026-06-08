"use client";

import {
  useRef,
  useState,
  type ChangeEvent,
  type KeyboardEvent,
} from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { useToast } from "@/components/providers/ToastProvider";
import {
  uploadChatAttachmentAction,
  type ChatAttachmentMeta,
} from "@/lib/actions/chat";

export interface ChatComposerProps {
  /**
   * Send the draft. Resolves to `true` on success so the field + attachment
   * clear; `false` keeps them so nothing typed/attached is lost.
   */
  onSend: (
    text: string,
    attachment: ChatAttachmentMeta | null,
  ) => Promise<boolean>;
  disabled?: boolean;
}

/** Mirrors the backend's 10 MB cap for a friendly pre-upload check. */
const MAX_BYTES = 10 * 1024 * 1024;

const ACCEPT =
  "image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip";

/**
 * Message input: Enter sends, Shift+Enter inserts a newline. Supports a single
 * file attachment per message — the file uploads to S3 immediately on pick and
 * its metadata rides along when the message is sent. The draft + attachment are
 * kept if the send fails. Auto-grows to the CSS max.
 */
export function ChatComposer({ onSend, disabled }: ChatComposerProps) {
  const t = useTranslations("chat");
  const { toast } = useToast();
  const [value, setValue] = useState("");
  const [sending, setSending] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [attachment, setAttachment] = useState<ChatAttachmentMeta | null>(null);
  const ref = useRef<HTMLTextAreaElement>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const grow = () => {
    const el = ref.current;
    if (!el) return;
    el.style.height = "auto";
    el.style.height = `${Math.min(el.scrollHeight, 140)}px`;
  };

  const onFile = async (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = ""; // allow re-picking the same file
    if (!file) return;

    if (file.size > MAX_BYTES) {
      toast({ tone: "err", title: t("attachmentTooLarge") });
      return;
    }

    setUploading(true);
    const fd = new FormData();
    fd.set("file", file);
    const res = await uploadChatAttachmentAction(fd);
    setUploading(false);

    if (res.ok) {
      setAttachment(res.data);
    } else {
      toast({ tone: "err", title: res.message || t("attachmentFailed") });
    }
  };

  const submit = async () => {
    const text = value.trim();
    if ((!text && !attachment) || sending || uploading || disabled) return;

    setSending(true);
    const ok = await onSend(text, attachment);
    setSending(false);

    if (ok) {
      setValue("");
      setAttachment(null);
      if (ref.current) ref.current.style.height = "auto";
    }
  };

  const onKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      void submit();
    }
  };

  const canSend =
    !disabled && !uploading && (value.trim().length > 0 || attachment !== null);

  return (
    <div className="chat-composer-wrap">
      {attachment && (
        <div className="chat-attach-chip">
          <Icon name="doc" size={14} />
          <span className="chat-attach-name">{attachment.name}</span>
          <button
            type="button"
            className="chat-attach-remove"
            onClick={() => setAttachment(null)}
            aria-label={t("removeAttachment")}
          >
            <Icon name="x" size={14} />
          </button>
        </div>
      )}

      <div className="chat-composer">
        <input
          ref={fileRef}
          type="file"
          hidden
          accept={ACCEPT}
          onChange={onFile}
        />
        <button
          type="button"
          className="chat-attach-btn"
          onClick={() => fileRef.current?.click()}
          disabled={disabled || uploading || sending}
          aria-label={t("attach")}
          title={t("attach")}
        >
          {uploading ? (
            <span className="btn-spinner" aria-hidden="true" />
          ) : (
            <Icon name="upload" />
          )}
        </button>

        <textarea
          ref={ref}
          className="input"
          rows={1}
          value={value}
          disabled={disabled}
          placeholder={t("composerPlaceholder")}
          onChange={(e) => {
            setValue(e.target.value);
            grow();
          }}
          onKeyDown={onKeyDown}
          aria-label={t("composerPlaceholder")}
        />

        <Button
          icon="send"
          onClick={() => void submit()}
          loading={sending}
          disabled={!canSend}
        >
          {t("send")}
        </Button>
      </div>
    </div>
  );
}
