import { notFound } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { ApiError } from "@/lib/api";
import { ownerReceipts, receiptFormData } from "@/lib/api/financeReceipts";
import type { ReceiptSort } from "@/lib/types/financeReceipts";
import { ReceiptManager } from "@/components/features/owner/ReceiptManager";

export const dynamic = "force-dynamic";

export default async function OwnerReceiptsPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ sort?: string }>;
}) {
  const { locale } = await params;
  const { sort: rawSort } = await searchParams;
  setRequestLocale(locale);
  const t = await getTranslations("finance.receipts");
  const sort: ReceiptSort = rawSort === "collected_at" ? "collected_at" : "received_at";

  const [receipts, form] = await Promise.all([
    ownerReceipts({ sort, per_page: 100 }),
    receiptFormData(),
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

      <ReceiptManager receipts={receipts.items} sort={sort} customers={form.customers} invoices={form.invoices} />
    </div>
  );
}
