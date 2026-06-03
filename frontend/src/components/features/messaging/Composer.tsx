"use client";

import {
  useRef,
  useState,
  type KeyboardEvent,
} from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";

export interface ComposerProps {
  /** Resolves to `true` on success so the field clears; `false` keeps the draft. */
  onSend: (content: string) => Promise<boolean>;
  disabled?: boolean;
}

/**
 * Message input: Enter sends, Shift+Enter inserts a newline. The draft is kept
 * if the send fails so the user never loses typed text. The textarea
 * auto-grows up to the CSS max-height.
 */
export function Composer({ onSend, disabled }: ComposerProps) {
  const t = useTranslations("messaging.messages");
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
    const content = value.trim();
    if (!content || sending) return;
    setSending(true);
    const ok = await onSend(content);
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
    <>
      <div className="msg-composer">
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
      <div className="msg-composer-hint">{t("sendHint")}</div>
    </>
  );
}
