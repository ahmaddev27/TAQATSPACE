"use client";

import type { ReactNode } from "react";
import { useTranslations } from "next-intl";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import type {
  MaskedSmsConfig,
  MaskedSmtpConfig,
  SmsProvider,
  SmtpEncryption,
} from "@/lib/types";

/* -------------------------------------------------------------------------- */
/*  Controlled form state                                                      */
/*                                                                            */
/*  Every value is a string so inputs stay controlled. Secrets are write-only: */
/*  the field starts blank (a "Set ••••" placeholder signals a stored value)   */
/*  and a blank submission preserves the stored secret server-side.            */
/* -------------------------------------------------------------------------- */

export interface SmtpFieldsState {
  host: string;
  port: string;
  username: string;
  password: string;
  encryption: SmtpEncryption;
  fromAddress: string;
  fromName: string;
}

export interface SmsFieldsState {
  provider: "" | SmsProvider;
  sender: string;
  username: string;
  password: string;
}

const SMTP_ENCRYPTIONS: SmtpEncryption[] = ["tls", "ssl", "none"];
const SMS_PROVIDERS: SmsProvider[] = ["hotsms", "mtcsms"];

/** Seed an SMTP form from the masked config (secrets are never present). */
export function buildSmtpState(masked: MaskedSmtpConfig | undefined): SmtpFieldsState {
  return {
    host: masked?.host ?? "",
    port: masked?.port != null ? String(masked.port) : "",
    username: masked?.username ?? "",
    password: "",
    encryption: masked?.encryption ?? "tls",
    fromAddress: masked?.from_address ?? "",
    fromName: masked?.from_name ?? "",
  };
}

/** Seed an SMS form from the masked config (secrets are never present). */
export function buildSmsState(masked: MaskedSmsConfig | undefined): SmsFieldsState {
  return {
    provider: masked?.provider ?? "",
    sender: masked?.sender ?? "",
    username: "",
    password: "",
  };
}

/* -------------------------------------------------------------------------- */
/*  SMTP fields                                                                 */
/* -------------------------------------------------------------------------- */

export interface SmtpFieldsProps {
  state: SmtpFieldsState;
  onChange: (patch: Partial<SmtpFieldsState>) => void;
  /** Whether a password is already stored (drives the masked placeholder). */
  hasPassword: boolean;
  errors?: Record<string, string[]>;
  /** Optional action slot (e.g. the "send test email" button). */
  action?: ReactNode;
}

export function SmtpFields({
  state,
  onChange,
  hasPassword,
  errors = {},
  action,
}: SmtpFieldsProps) {
  const t = useTranslations("messaging.settings.smtp");

  return (
    <div className="stack" style={{ gap: 16 }}>
      <div>
        <h3 className="h3">{t("heading")}</h3>
        <p className="muted" style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
          {t("description")}
        </p>
      </div>

      <div className="grid2">
        <Field label={t("host")} error={errors["smtp.host"]?.[0]}>
          <Input
            className="ltr"
            placeholder={t("hostPlaceholder")}
            value={state.host}
            onChange={(e) => onChange({ host: e.target.value })}
          />
        </Field>
        <Field label={t("port")} error={errors["smtp.port"]?.[0]}>
          <Input
            className="tnum ltr"
            inputMode="numeric"
            placeholder={t("portPlaceholder")}
            value={state.port}
            onChange={(e) => onChange({ port: e.target.value })}
          />
        </Field>
      </div>

      <div className="grid2">
        <Field label={t("username")} error={errors["smtp.username"]?.[0]}>
          <Input
            className="ltr"
            autoComplete="off"
            value={state.username}
            onChange={(e) => onChange({ username: e.target.value })}
          />
        </Field>
        <Field
          label={t("password")}
          hint={hasPassword ? t("passwordKeepHint") : t("passwordNewHint")}
          error={errors["smtp.password"]?.[0]}
        >
          <Input
            type="password"
            className="ltr"
            autoComplete="new-password"
            placeholder={hasPassword ? t("passwordSetPlaceholder") : ""}
            value={state.password}
            onChange={(e) => onChange({ password: e.target.value })}
          />
        </Field>
      </div>

      <div className="grid2">
        <Field label={t("encryption")} error={errors["smtp.encryption"]?.[0]}>
          <Select
            value={state.encryption}
            onChange={(e) =>
              onChange({ encryption: e.target.value as SmtpEncryption })
            }
          >
            {SMTP_ENCRYPTIONS.map((enc) => (
              <option key={enc} value={enc}>
                {t(
                  `encryption${enc.charAt(0).toUpperCase()}${enc.slice(1)}` as never,
                )}
              </option>
            ))}
          </Select>
        </Field>
        <Field label={t("fromName")} error={errors["smtp.from_name"]?.[0]}>
          <Input
            placeholder={t("fromNamePlaceholder")}
            value={state.fromName}
            onChange={(e) => onChange({ fromName: e.target.value })}
          />
        </Field>
      </div>

      <Field label={t("fromAddress")} error={errors["smtp.from_address"]?.[0]}>
        <Input
          className="ltr"
          inputMode="email"
          placeholder={t("fromAddressPlaceholder")}
          value={state.fromAddress}
          onChange={(e) => onChange({ fromAddress: e.target.value })}
        />
      </Field>

      {action && <div className="row" style={{ gap: 8 }}>{action}</div>}
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  SMS fields                                                                  */
/* -------------------------------------------------------------------------- */

export interface SmsFieldsProps {
  state: SmsFieldsState;
  onChange: (patch: Partial<SmsFieldsState>) => void;
  /** Whether SMS credentials are already stored. */
  hasCredentials: boolean;
  errors?: Record<string, string[]>;
  /** Optional action slot (e.g. the "send test SMS" button). */
  action?: ReactNode;
}

export function SmsFields({
  state,
  onChange,
  hasCredentials,
  errors = {},
  action,
}: SmsFieldsProps) {
  const t = useTranslations("messaging.settings.sms");

  return (
    <div className="stack" style={{ gap: 16 }}>
      <div>
        <h3 className="h3">{t("heading")}</h3>
        <p className="muted" style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
          {t("description")}
        </p>
      </div>

      <div className="grid2">
        <Field label={t("provider")} error={errors["sms.provider"]?.[0]}>
          <Select
            value={state.provider}
            onChange={(e) =>
              onChange({ provider: e.target.value as "" | SmsProvider })
            }
          >
            <option value="">{t("providerPlaceholder")}</option>
            {SMS_PROVIDERS.map((provider) => (
              <option key={provider} value={provider}>
                {t(
                  `provider${provider.charAt(0).toUpperCase()}${provider.slice(1)}` as never,
                )}
              </option>
            ))}
          </Select>
        </Field>
        <Field label={t("sender")} error={errors["sms.sender"]?.[0]}>
          <Input
            className="ltr"
            placeholder={t("senderPlaceholder")}
            value={state.sender}
            onChange={(e) => onChange({ sender: e.target.value })}
          />
        </Field>
      </div>

      <div className="grid2">
        <Field label={t("username")} error={errors["sms.username"]?.[0]}>
          <Input
            className="ltr"
            autoComplete="off"
            placeholder={hasCredentials ? t("passwordSetPlaceholder") : ""}
            value={state.username}
            onChange={(e) => onChange({ username: e.target.value })}
          />
        </Field>
        <Field
          label={t("password")}
          hint={hasCredentials ? t("credentialsKeepHint") : t("credentialsNewHint")}
          error={errors["sms.password"]?.[0]}
        >
          <Input
            type="password"
            className="ltr"
            autoComplete="new-password"
            placeholder={hasCredentials ? t("passwordSetPlaceholder") : ""}
            value={state.password}
            onChange={(e) => onChange({ password: e.target.value })}
          />
        </Field>
      </div>

      {action && <div className="row" style={{ gap: 8 }}>{action}</div>}
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Payload builders (controlled state -> wire shape)                          */
/* -------------------------------------------------------------------------- */

/**
 * Map SMTP form state to the write payload. The password is only sent when the
 * user typed one (blank preserves the stored secret server-side). Port is
 * coerced to a number, or `null` when cleared.
 */
export function smtpPayload(state: SmtpFieldsState) {
  const port = state.port.trim();
  return {
    host: state.host.trim() || null,
    port: port === "" ? null : Number(port),
    username: state.username.trim() || null,
    encryption: state.encryption,
    from_address: state.fromAddress.trim() || null,
    from_name: state.fromName.trim() || null,
    ...(state.password !== "" ? { password: state.password } : {}),
  };
}

/**
 * Map SMS form state to the write payload. Secrets (username, password) are
 * only sent when typed; a blank value preserves the stored secret.
 */
export function smsPayload(state: SmsFieldsState) {
  return {
    provider: state.provider === "" ? null : state.provider,
    sender: state.sender.trim() || null,
    ...(state.username !== "" ? { username: state.username } : {}),
    ...(state.password !== "" ? { password: state.password } : {}),
  };
}
