import { notFound } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { ApiError } from "@/lib/api";
import { receiptFormData } from "@/lib/api/financeReceipts";
import { ownerPaymentVouchers, ownerWallets } from "@/lib/api/financeWallet";
import { WalletManager } from "@/components/features/owner/WalletManager";

export const dynamic = "force-dynamic";

export default async function OwnerWalletPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("finance.wallet");

  const [wallets, vouchers, form] = await Promise.all([
    ownerWallets(),
    ownerPaymentVouchers(),
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

      <WalletManager wallets={wallets.items} vouchers={vouchers.items} customers={form.customers} invoices={form.invoices} />
    </div>
  );
}
