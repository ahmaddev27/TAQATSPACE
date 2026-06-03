import { getTranslations, setRequestLocale } from "next-intl/server";
import { getOwnerWorkspace } from "@/lib/actions/owner";
import { Icon } from "@/components/ui/Icon";
import { SettingsTabs } from "@/components/features/owner/SettingsTabs";

export const dynamic = "force-dynamic";

export default async function OwnerSettingsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner");

  const workspace = await getOwnerWorkspace();

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("settings.title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("settings.subtitle")}
          </p>
        </div>
      </div>

      <div style={{ marginTop: 16 }}>
        {workspace ? (
          <SettingsTabs workspace={workspace} locale={locale} />
        ) : (
          <div className="empty-state" style={{ minHeight: 280 }}>
            <div className="st-ico amber" style={{ width: 48, height: 48 }}>
              <Icon name="building" />
            </div>
            <div>
              <div className="h3">{t("dashboard.noWorkspace")}</div>
              <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
                {t("dashboard.noWorkspaceBody")}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
