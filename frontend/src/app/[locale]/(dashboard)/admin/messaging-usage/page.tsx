import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getAdminMessagingUsage } from "@/lib/api/admin";

export const dynamic = "force-dynamic";

/**
 * Admin messaging-usage report: which workspaces send broadcasts through Taqat's
 * shared platform quota vs their own accounts, and how many messages each sent.
 */
export default async function AdminMessagingUsagePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_messaging");
  const t = await getTranslations("admin.messagingUsage");

  const rows = await getAdminMessagingUsage();

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

      <div className="table-wrap" style={{ marginTop: 16 }}>
        {rows.length === 0 ? (
          <div className="empty-state">
            <div>
              <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                {t("empty")}
              </div>
              <div style={{ fontSize: "var(--fs-sm)" }}>{t("emptyBody")}</div>
            </div>
          </div>
        ) : (
          <table className="tbl">
            <thead>
              <tr>
                <th>{t("workspace")}</th>
                <th>{t("platformEmail")}</th>
                <th>{t("platformSms")}</th>
                <th>{t("ownEmail")}</th>
                <th>{t("ownSms")}</th>
                <th>{t("platformTotal")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.workspace_id}>
                  <td style={{ fontWeight: 600 }}>{r.workspace_name}</td>
                  <td className="cell-num">{r.platform_email}</td>
                  <td className="cell-num">{r.platform_sms}</td>
                  <td className="cell-num">{r.own_email}</td>
                  <td className="cell-num">{r.own_sms}</td>
                  <td
                    className="cell-num"
                    style={{
                      fontWeight: 700,
                      color: r.platform_total > 0 ? "var(--primary)" : undefined,
                    }}
                  >
                    {r.platform_total}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
