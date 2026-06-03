import { getTranslations, setRequestLocale } from "next-intl/server";
import { listMembers } from "@/lib/api/members";
import { MembersTable } from "@/components/features/owner/MembersTable";

export const dynamic = "force-dynamic";

export default async function OwnerMembersPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.members");

  const { members } = await listMembers({ status: "all", per_page: 200 });

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
        <MembersTable members={members} />
      </div>
    </div>
  );
}
