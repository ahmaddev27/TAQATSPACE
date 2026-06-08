"use client";

import { useRef, useState, type KeyboardEvent } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";

export interface ChatComposerProps {
  /** Resolves to `true` on success so the field clears; `false` keeps the draft. */
  onSend: (text: string) => Promise<boolean>;
  disabled?: boolean;
}

/**
 * Message input: Enter sends, Shift+Enter inserts a newline. The draft is kept
 * if the send fails so typed text is never lost. Auto-grows to the CSS max.
 */
export function ChatComposer({ onSend, disabled }: ChatComposerProps) {
  const t = useTranslations("chat");
  const [value, setValue] = useState("");
  const [sending, setSending] = useState(false);
  const ref = useRef<HTMLTextAreaElement>(null);

  const grow = () => {
    const el = ref.current;
    if (!el) return;
    el.style.height = "auto";
    el.style.height = `${Math.min(el.scrollHeight, 140)}px`;
  };

  const submit = async () => {
    const text = value.trim();
    if (!text || sending || disabled) return;
    setSending(true);
    const ok = await onSend(text);
    setSending(false);
    if (ok) {
      setValue("");
      if (ref.current) ref.current.style.height = "auto";
    }
  };

  const onKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      void submit();
    }
  };

  return (
    <div className="chat-composer">
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
        disabled={disabled || value.trim().length === 0}
      >
        {t("send")}
      </Button>
    </div>
  );
}
