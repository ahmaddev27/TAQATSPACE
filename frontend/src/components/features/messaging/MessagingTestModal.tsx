"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { useToast } from "@/components/providers/ToastProvider";
import { sendMessagingTest } from "@/lib/actions/admin";
import type { MessagingTestChannel } from "@/lib/types";

export interface MessagingTestModalProps {
  channel: MessagingTestChannel;
  onClose: () => void;
}

/**
 * Prompts for a destination (email or phone) and sends a test message through
 * the saved platform config. The backend reports driver errors as a 422 with a
 * friendly message, surfaced here as an error toast rather than a thrown error.
 */
export function MessagingTestModal({ channel, onClose }: MessagingTestModalProps) {
  const t = useTranslations("messaging.settings");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [to, setTo] = useState("");

  const channelKey = channel === "email" ? "smtp" : "sms";
  const isEmail = channel === "email";

  const submit = () => {
    const destination = to.trim();
    if (destination === "") {
      toast({ tone: "err", title: t("test.missingDestination") });
      return;
    }

    startTransition(async () => {
      const res = await sendMessagingTest(channel, destination);
      if (res.ok) {
        toast({ tone: "ok", title: res.message ?? t("test.sent") });
        onClose();
      } else {
        toast({ tone: "err", title: t("test.failed"), body: res.message });
      }
    });
  };

  return (
    <Modal
      icon={isEmail ? "mail" : "chat"}
      title={t(`${channelKey}.testPromptTitle` as never)}
      onClose={onClose}
      closeLabel={t("test.cancel")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={pending}>
            {t("test.cancel")}
          </Button>
          <Button variant="primary" icon="send" loading={pending} onClick={submit}>
            {pending ? t("test.sending") : t("test.send")}
          </Button>
        </>
      }
    >
      <Field label={t(`${channelKey}.testPromptLabel` as never)}>
        <Input
          className="ltr"
          autoFocus
          type={isEmail ? "email" : "tel"}
          inputMode={isEmail ? "email" : "tel"}
          placeholder={t(`${channelKey}.testPromptPlaceholder` as never)}
          value={to}
          onChange={(e) => setTo(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") submit();
          }}
        />
      </Field>
    </Modal>
  );
}
