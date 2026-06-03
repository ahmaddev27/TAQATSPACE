import { getTranslations, setRequestLocale } from "next-intl/server";
import { listAnnouncements } from "@/lib/api/announcements";
import { AnnouncementsManager } from "@/components/features/announcements/AnnouncementsManager";

export const dynamic = "force-dynamic";

export default async function OwnerAnnouncementsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("announcements");

  const announcements = await listAnnouncements();

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
        <AnnouncementsManager announcements={announcements} />
      </div>
    </div>
  );
}
