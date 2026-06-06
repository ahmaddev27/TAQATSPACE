"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import { updatePlatformMessaging } from "@/lib/actions/admin";
import {
  SmsFields,
  SmtpFields,
  buildSmsState,
  buildSmtpState,
  smsPayload,
  smtpPayload,
  type SmsFieldsState,
  type SmtpFieldsState,
} from "@/components/features/messaging/MessagingSettingsFields";
import { MessagingTestModal } from "@/components/features/messaging/MessagingTestModal";
import type { MessagingTestChannel, PlatformMessagingConfig } from "@/lib/types";

export interface MessagingSettingsFormProps {
  initial: PlatformMessagingConfig;
}

/**
 * Super-admin editor for Taqat's own SMTP + SMS accounts. One Save persists
 * both blocks; secrets are write-only (blank keeps the stored value). Each
 * channel offers a "send test" button that prompts for a destination.
 */
export function MessagingSettingsForm({ initial }: MessagingSettingsFormProps) {
  const t = useTranslations("messaging.settings");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [smtp, setSmtp] = useState<SmtpFieldsState>(() => buildSmtpState(initial.smtp));
  const [sms, setSms] = useState<SmsFieldsState>(() => buildSmsState(initial.sms));
  const [hasPassword, setHasPassword] = useState(initial.smtp.has_password);
  const [hasCredentials, setHasCredentials] = useState(initial.sms.has_credentials);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [testChannel, setTestChannel] = useState<MessagingTestChannel | null>(null);

  const patchSmtp = (patch: Partial<SmtpFieldsState>) =>
    setSmtp((s) => ({ ...s, ...patch }));
  const patchSms = (patch: Partial<SmsFieldsState>) =>
    setSms((s) => ({ ...s, ...patch }));

  const save = () => {
    startTransition(async () => {
      const res = await updatePlatformMessaging({
        smtp: smtpPayload(smtp),
        sms: smsPayload(sms),
      });

      if (res.ok) {
        setErrors({});
        // Reflect the refreshed masked state and clear the entered secrets so a
        // re-save does not resend them.
        if (res.data) {
          setHasPassword(res.data.smtp.has_password);
          setHasCredentials(res.data.sms.has_credentials);
        }
        setSmtp((s) => ({ ...s, password: "" }));
        setSms((s) => ({ ...s, username: "", password: "" }));
        toast({ tone: "ok", title: res.message ?? t("admin.saved") });
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("admin.saveFailed"), body: res.message });
      }
    });
  };

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="card card-pad">
        <SmtpFields
          state={smtp}
          onChange={patchSmtp}
          hasPassword={hasPassword}
          errors={errors}
          action={
            <Button
              variant="secondary"
              icon="mail"
              onClick={() => setTestChannel("email")}
            >
              {t("smtp.testButton")}
            </Button>
          }
        />
      </div>

      <div className="card card-pad">
        <SmsFields
          state={sms}
          onChange={patchSms}
          hasCredentials={hasCredentials}
          errors={errors}
          action={
            <Button
              variant="secondary"
              icon="chat"
              onClick={() => setTestChannel("sms")}
            >
              {t("sms.testButton")}
            </Button>
          }
        />
      </div>

      <Button
        variant="primary"
        icon="check"
        loading={pending}
        onClick={save}
        style={{ alignSelf: "flex-start" }}
      >
        {pending ? t("admin.saving") : t("admin.save")}
      </Button>

      {testChannel && (
        <MessagingTestModal
          channel={testChannel}
          onClose={() => setTestChannel(null)}
        />
      )}
    </div>
  );
}
