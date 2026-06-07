import { getTranslations, setRequestLocale } from "next-intl/server";
import { ownerResources } from "@/lib/api/management";
import { ResourcesTable } from "@/components/features/owner/ResourcesTable";

export const dynamic = "force-dynamic";

export default async function OwnerResourcesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("management.resources");

  const { resources, summary } = await ownerResources({ per_page: 200 });

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
        <ResourcesTable resources={resources} summary={summary} />
      </div>
    </div>
  );
}
