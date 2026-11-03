import { notFound } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { ApiError } from "@/lib/api";
import { ownerCatalogCategories, ownerCatalogItems } from "@/lib/api/finance";
import { CatalogManager } from "@/components/features/owner/CatalogManager";

export const dynamic = "force-dynamic";

export default async function OwnerCatalogPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("finance.catalog");

  const [categories, items] = await Promise.all([
    ownerCatalogCategories(),
    ownerCatalogItems(),
  ]).catch((error: unknown) => {
    // The backend answers 404 while Finance v2 is off for the workspace.
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  });

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      <CatalogManager
        categories={categories.categories}
        items={items.items}
      />
    </div>
  );
}
