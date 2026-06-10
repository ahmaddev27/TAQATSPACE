import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getPlatformMessaging } from "@/lib/api/admin";
import { MessagingSettingsForm } from "@/components/features/admin/MessagingSettingsForm";
import type { PlatformMessagingConfig } from "@/lib/types";

export const dynamic = "force-dynamic";

/** Empty masked config used as a safe fallback if the read fails. */
const EMPTY_CONFIG: PlatformMessagingConfig = {
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

export default async function AdminMessagingSettingsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_messaging");
  const t = await getTranslations("messaging.settings.admin");

  // A read failure must not break the page; fall back to an empty config.
  let config: PlatformMessagingConfig = EMPTY_CONFIG;
  try {
    config = await getPlatformMessaging();
  } catch {
    config = EMPTY_CONFIG;
  }

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
      </div>

      <div style={{ marginTop: 16 }}>
        <MessagingSettingsForm initial={config} />
      </div>
    </div>
  );
}
