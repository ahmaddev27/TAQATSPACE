import { getTranslations, setRequestLocale } from "next-intl/server";
import { listNotifications } from "@/lib/api/notifications";
import { NotificationsView } from "@/components/features/messaging/NotificationsView";

export const dynamic = "force-dynamic";

export default async function OwnerNotificationsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("messaging.notifications");

  const { notifications, meta } = await listNotifications({ per_page: 50 });

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

      <NotificationsView
        initial={notifications}
        initialUnread={meta.unread_count}
        role="owner"
      />
    </div>
  );
}
