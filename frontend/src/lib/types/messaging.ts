/**
 * Masked messaging configuration shapes, mirroring the backend's
 * {@link \App\Services\MessagingSettingsService} masked output. Secrets are
 * NEVER present: the API returns `has_*` booleans instead, and a blank secret
 * on write preserves the stored value.
 *
 * @see backend/app/Services/MessagingSettingsService.php (maskSmtp / maskSms)
 */

/** Supported SMTP transport encryptions. */
export type SmtpEncryption = "tls" | "ssl" | "none";

/** Supported SMS providers (mirrors `SmsService::providers()`). */
export type SmsProvider = "hotsms" | "mtcsms";

/** Masked SMTP block: non-secret fields plus a `has_password` flag. */
export interface MaskedSmtpConfig {
  host: string | null;
  port: number | null;
  username: string | null;
  encryption: SmtpEncryption | null;
  from_address: string | null;
  from_name: string | null;
  has_password: boolean;
}

/** Masked SMS block: non-secret fields plus a `has_credentials` flag. */
export interface MaskedSmsConfig {
  provider: SmsProvider | null;
  sender: string | null;
  has_credentials: boolean;
}

/** Platform-level (Super Admin) masked messaging config. */
export interface PlatformMessagingConfig {
  smtp: MaskedSmtpConfig;
  sms: MaskedSmsConfig;
}

/** Workspace-level (owner) masked messaging config: adds the inherit toggle. */
export interface WorkspaceMessagingConfig extends PlatformMessagingConfig {
  /** When true, the workspace inherits Taqat's platform accounts. */
  use_platform: boolean;
}

/* -------------------------------------------------------------------------- */
/*  Write payloads (secrets optional; blank = keep stored)                     */
/* -------------------------------------------------------------------------- */

export interface SmtpUpdateInput {
  host?: string | null;
  port?: number | null;
  username?: string | null;
  /** Omit or send blank to keep the stored password. */
  password?: string | null;
  encryption?: SmtpEncryption | null;
  from_address?: string | null;
  from_name?: string | null;
}

export interface SmsUpdateInput {
  provider?: SmsProvider | null;
  sender?: string | null;
  username?: string | null;
  /** Omit or send blank to keep the stored secret. */
  password?: string | null;
  api_key?: string | null;
}

/** Body for `PUT /admin/settings/messaging`. */
export interface PlatformMessagingUpdateInput {
  smtp?: SmtpUpdateInput;
  sms?: SmsUpdateInput;
}

/** Body for `PUT /workspace/messaging`. */
export interface WorkspaceMessagingUpdateInput extends PlatformMessagingUpdateInput {
  use_platform?: boolean;
}

/** The two channels a test message can be sent through. */
export type MessagingTestChannel = "email" | "sms";
