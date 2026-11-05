import { getTranslations, setRequestLocale } from "next-intl/server";
import { serverFetch } from "@/lib/api";
import { getOwnerWorkspace } from "@/lib/actions/owner";
import { ownerPendingApprovals } from "@/lib/api/approvals";
import { Icon } from "@/components/ui/Icon";
import { SettingsTabs } from "@/components/features/owner/SettingsTabs";
import type { ApiEnvelope, User } from "@/lib/types";

export const dynamic = "force-dynamic";

export default async function OwnerSettingsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner");

  const [workspace, pendingApprovals, me] = await Promise.all([
    getOwnerWorkspace(),
    ownerPendingApprovals(),
    serverFetch<ApiEnvelope<{ user: User }>>("/auth/me"),
  ]);

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
          <SettingsTabs
            workspace={workspace}
            locale={locale}
            pendingApprovals={pendingApprovals}
            networkEnabled={me.data.user.features?.network === true}
          />
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
