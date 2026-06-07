import { getTranslations, setRequestLocale } from "next-intl/server";
import { ownerExpenses } from "@/lib/api/management";
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

  const { expenses, summary } = await ownerExpenses({ per_page: 200 });

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
        <ExpensesTable expenses={expenses} summary={summary} />
      </div>
    </div>
  );
}
