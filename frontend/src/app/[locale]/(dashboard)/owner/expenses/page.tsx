import { getTranslations, setRequestLocale } from "next-intl/server";
import { ApiError } from "@/lib/api";
import { ownerCatalogCategories } from "@/lib/api/finance";
import { ownerExpenses } from "@/lib/api/management";
import type { CatalogCategory } from "@/lib/types/finance";
import { ExpensesTable } from "@/components/features/owner/ExpensesTable";

export const dynamic = "force-dynamic";

export default async function OwnerExpensesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("management.expenses");

  const [{ expenses, summary }, catalogCategories] = await Promise.all([
    ownerExpenses({ per_page: 200 }),
    expenseCatalogCategories(),
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
        <ExpensesTable
          expenses={expenses}
          summary={summary}
          catalogCategories={catalogCategories}
        />
      </div>
    </div>
  );
}

/** Expense categories from the catalog; null while Finance v2 is off (404). */
async function expenseCatalogCategories(): Promise<CatalogCategory[] | null> {
  try {
    const { categories } = await ownerCatalogCategories();
    return categories.filter((c) => c.kind === "expense" && !c.is_archived);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}
