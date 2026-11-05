import { notFound } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { ApiError } from "@/lib/api";
import { ownerCatalogCategories } from "@/lib/api/finance";
import { ImportWizard } from "@/components/features/owner/ImportWizard";

export const dynamic = "force-dynamic";

export default async function OwnerImportPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("finance.import");

  // The import page reads nothing itself; this call only learns whether Finance
  // v2 is on, because the backend answers 404 while it is off for the workspace.
  await ownerCatalogCategories().catch((error: unknown) => {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  });

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      <ImportWizard />
    </div>
  );
}
