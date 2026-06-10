import { getTranslations, setRequestLocale } from "next-intl/server";
import { listContactMessages } from "@/lib/api/contact";
import { requireAdminPermission } from "@/lib/admin-guard";
import { ContactInbox } from "@/components/features/admin/ContactInbox";

export const dynamic = "force-dynamic";

export default async function AdminMessagesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_messaging");

  const t = await getTranslations("admin.contact");
  const { messages } = await listContactMessages();

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

      <div className="stack" style={{ marginTop: 8 }}>
        <ContactInbox messages={messages} />
      </div>
    </div>
  );
}
