"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { useToast } from "@/components/providers/ToastProvider";
import { updateWorkspaceMessaging } from "@/lib/actions/owner";
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
import type { WorkspaceMessagingConfig } from "@/lib/types";

export interface MessagingTabProps {
  /** Masked messaging block from the workspace resource (secrets absent). */
  messaging: WorkspaceMessagingConfig | undefined;
}

/** Empty masked config used when a legacy workspace has no messaging block. */
const EMPTY: WorkspaceMessagingConfig = {
  use_platform: true,
  smtp: {
    host: null,
    port: null,
    username: null,
    encryption: null,
    from_address: null,
    from_name: null,
    has_password: false,
  },
  sms: { provider: null, sender: null, has_credentials: false },
};

/**
 * Owner editor for the workspace's messaging accounts. The "use Taqat accounts"
 * toggle inherits the platform config; turning it off reveals the workspace's
 * own SMTP + SMS fields (write-only secrets, same shape as the admin form).
 */
export function MessagingTab({ messaging }: MessagingTabProps) {
  const t = useTranslations("messaging.settings");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const config = messaging ?? EMPTY;

  const [usePlatform, setUsePlatform] = useState(config.use_platform);
  const [smtp, setSmtp] = useState<SmtpFieldsState>(() => buildSmtpState(config.smtp));
  const [sms, setSms] = useState<SmsFieldsState>(() => buildSmsState(config.sms));
  const [hasPassword, setHasPassword] = useState(config.smtp.has_password);
  const [hasCredentials, setHasCredentials] = useState(config.sms.has_credentials);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const patchSmtp = (patch: Partial<SmtpFieldsState>) =>
    setSmtp((s) => ({ ...s, ...patch }));
  const patchSms = (patch: Partial<SmsFieldsState>) =>
    setSms((s) => ({ ...s, ...patch }));

  const save = () => {
    // When inheriting the platform accounts, only the toggle needs persisting;
    // the workspace's own (possibly empty) fields are left untouched.
    const payload = usePlatform
      ? { use_platform: true }
      : {
          use_platform: false,
          smtp: smtpPayload(smtp),
          sms: smsPayload(sms),
        };

    startTransition(async () => {
      const res = await updateWorkspaceMessaging(payload);
      if (res.ok) {
        setErrors({});
        if (res.data) {
          setHasPassword(res.data.smtp.has_password);
          setHasCredentials(res.data.sms.has_credentials);
        }
        setSmtp((s) => ({ ...s, password: "" }));
        setSms((s) => ({ ...s, username: "", password: "" }));
        toast({ tone: "ok", title: res.message ?? t("owner.saved") });
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("owner.saveFailed"), body: res.message });
      }
    });
  };

  return (
    <div className="stack" style={{ gap: 18 }}>
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {t("owner.subtitle")}
      </p>

      <Checkbox
        checked={usePlatform}
        onChange={(e) => setUsePlatform(e.target.checked)}
      >
        <span className="hours-day">{t("owner.usePlatformLabel")}</span>
      </Checkbox>
      <p className="muted" style={{ fontSize: "var(--fs-sm)", marginTop: -8 }}>
        {t("owner.usePlatformHint")}
      </p>

      {usePlatform ? (
        <Alert tone="info">{t("owner.platformNote")}</Alert>
      ) : (
        <div className="stack" style={{ gap: 18 }}>
          <Alert tone="info">{t("owner.ownAccountsNote")}</Alert>

          <div className="card card-pad">
            <SmtpFields
              state={smtp}
              onChange={patchSmtp}
              hasPassword={hasPassword}
              errors={errors}
            />
          </div>

          <div className="card card-pad">
            <SmsFields
              state={sms}
              onChange={patchSms}
              hasCredentials={hasCredentials}
              errors={errors}
            />
          </div>
        </div>
      )}

      <Button
        variant="primary"
        icon="check"
        loading={pending}
        onClick={save}
        style={{ alignSelf: "flex-start" }}
      >
        {pending ? t("owner.saving") : t("owner.save")}
      </Button>
    </div>
  );
}
