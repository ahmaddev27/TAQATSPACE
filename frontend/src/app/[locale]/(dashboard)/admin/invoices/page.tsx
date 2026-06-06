import { getTranslations, setRequestLocale } from "next-intl/server";
import { getAllAdminInvoices } from "@/lib/api/admin";
import { InvoicesTable } from "@/components/features/admin/InvoicesTable";
import { ExportCsvLink } from "@/components/features/admin/ExportCsvLink";

export const dynamic = "force-dynamic";

export default async function AdminInvoicesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("admin.invoices");

  const invoices = await getAllAdminInvoices();

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
        <ExportCsvLink type="invoices" label={t("exportCsv")} />
      </div>

      <div style={{ marginTop: 16 }}>
        <InvoicesTable invoices={invoices} />
      </div>
    </div>
  );
}
