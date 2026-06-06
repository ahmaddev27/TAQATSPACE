import { getTranslations, setRequestLocale } from "next-intl/server";
import { listMembers } from "@/lib/api/members";
import { OwnerBroadcastForm } from "@/components/features/owner/OwnerBroadcastForm";
import type { RecipientOption } from "@/components/features/messaging/BroadcastComposer";

export const dynamic = "force-dynamic";

export default async function OwnerMessagingPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("broadcast.owner");

  // The "specific" picker reuses the owner's existing member roster (scoped to
  // this workspace server-side). A read failure falls back to an empty roster.
  let recipients: RecipientOption[] = [];
  try {
    const { members } = await listMembers({ status: "all", per_page: 200 });
    recipients = members.map((m) => ({
      id: m.user.id,
      name: m.user.name,
      email: m.user.email ?? null,
      phone: m.user.phone ?? null,
    }));
  } catch {
    recipients = [];
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
        <OwnerBroadcastForm recipients={recipients} />
      </div>
    </div>
  );
}
