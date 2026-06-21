import { getTranslations, setRequestLocale } from "next-intl/server";
import { getAdminCities } from "@/lib/api/cities";
import { requireAdminPermission } from "@/lib/admin-guard";
import { CitiesTable } from "@/components/features/admin/CitiesTable";

export const dynamic = "force-dynamic";

export default async function AdminCitiesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_content");

  const t = await getTranslations("admin.cities");
  const cities = await getAdminCities();

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
        <CitiesTable cities={cities} />
      </div>
    </div>
  );
}
