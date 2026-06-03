import { getTranslations, setRequestLocale } from "next-intl/server";
import { memberInvoices } from "@/lib/api/invoices";
import { Alert } from "@/components/ui/Alert";
import { MemberInvoicesList } from "@/components/features/invoices/MemberInvoicesList";

export const dynamic = "force-dynamic";

export default async function FreelancerInvoicesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("invoices.member");

  const { invoices } = await memberInvoices({ per_page: 200 });
  const overdueCount = invoices.filter((inv) => inv.status === "overdue").length;

  return (
    <div className="stack" style={{ gap: 24 }}>
      <div className="stack" style={{ gap: 4 }}>
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      {overdueCount > 0 && (
        <Alert
          tone="danger"
          icon="alert"
          title={t("overdueAlert.title", { count: overdueCount })}
        >
          {t("overdueAlert.body")}
        </Alert>
      )}

      <MemberInvoicesList invoices={invoices} />
    </div>
  );
}
