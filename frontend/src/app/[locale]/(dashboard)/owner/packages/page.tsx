import { getTranslations, setRequestLocale } from "next-intl/server";
import { listPackages } from "@/lib/api/packages";
import { listMembers } from "@/lib/api/members";
import { PackagesManager } from "@/components/features/owner/PackagesManager";

export const dynamic = "force-dynamic";

export default async function OwnerPackagesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.packages");

  const [packages, memberResult] = await Promise.all([
    listPackages(),
    listMembers({ status: "active", per_page: 200 }),
  ]);

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
        <PackagesManager packages={packages} members={memberResult.members} />
      </div>
    </div>
  );
}
