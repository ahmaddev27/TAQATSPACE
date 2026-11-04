import { Link } from "@/i18n/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getHourPackages } from "@/lib/api/hourPackages";
import { HourPackagesTable } from "@/components/features/admin/HourPackagesTable";

export const dynamic = "force-dynamic";

export default async function AdminHourPackagesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_billing");
  const t = await getTranslations("admin.hourPackages");

  const packages = await getHourPackages();

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
        <Link className="btn btn-secondary" href="/admin/hour-packages/purchases">
          {t("purchasesLink")}
        </Link>
      </div>

      <div style={{ marginTop: 16 }}>
        <HourPackagesTable packages={packages} />
      </div>
    </div>
  );
}
