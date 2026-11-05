import { notFound } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { ApiError } from "@/lib/api";
import { ownerCreditNotes } from "@/lib/api/financeCreditNotes";
import { CreditNoteManager } from "@/components/features/owner/CreditNoteManager";

export const dynamic = "force-dynamic";

export default async function OwnerCreditNotesPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("finance.creditNotes");

  const creditNotes = await ownerCreditNotes({ per_page: 100 }).catch((error: unknown) => {
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

      <CreditNoteManager creditNotes={creditNotes.items} />
    </div>
  );
}
