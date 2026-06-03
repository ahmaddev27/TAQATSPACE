"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Modal } from "@/components/ui/Modal";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import { broadcastMessage } from "@/lib/actions/messaging";

export interface BroadcastModalProps {
  /** Active members who will receive the broadcast (for the recipient count). */
  recipientCount: number;
  onClose: () => void;
  /** Called after a successful broadcast so the parent can refresh threads. */
  onSent?: () => void;
}

/** Compose-and-send dialog for a workspace-wide broadcast message. */
export function BroadcastModal({
  recipientCount,
  onClose,
  onSent,
}: BroadcastModalProps) {
  const t = useTranslations("messaging.broadcastModal");
  const { toast } = useToast();
  const [content, setContent] = useState("");
  const [sending, setSending] = useState(false);

  const send = async () => {
    const text = content.trim();
    if (!text) {
      toast({ tone: "err", title: t("emptyError") });
      return;
    }
    setSending(true);
    const res = await broadcastMessage(text);
    setSending(false);
    if (res.ok) {
      toast({ tone: "ok", title: t("sentToast") });
      onSent?.();
      onClose();
    } else {
      toast({ tone: "err", title: t("failed"), body: res.message });
    }
  };

  return (
    <Modal
      title={t("title")}
      icon="megaphone"
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={sending}>
            {t("cancel")}
          </Button>
          <Button icon="send" onClick={() => void send()} loading={sending}>
            {t("send")}
          </Button>
        </>
      }
    >
      <p className="muted" style={{ marginBottom: 12, fontSize: "var(--fs-sm)" }}>
        {t("description")}
      </p>
      <div
        className="badge badge-info"
        style={{ marginBottom: 14 }}
        aria-live="polite"
      >
        {t("recipients", { count: recipientCount })}
      </div>
      <textarea
        className="input"
        rows={5}
        value={content}
        onChange={(e) => setContent(e.target.value)}
        placeholder={t("placeholder")}
        aria-label={t("placeholder")}
        style={{ resize: "vertical", minHeight: 120 }}
        autoFocus
      />
    </Modal>
  );
}
