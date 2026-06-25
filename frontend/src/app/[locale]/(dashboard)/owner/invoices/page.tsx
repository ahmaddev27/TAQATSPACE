import { getTranslations, setRequestLocale } from "next-intl/server";
import { ownerInvoices } from "@/lib/api/invoices";
import { listMembers } from "@/lib/api/members";
import { StatTile } from "@/components/ui/StatTile";
import { InvoicesTable } from "@/components/features/invoices/InvoicesTable";
import { invoiceMoney } from "@/components/features/invoices/format";
import type { InvoiceMemberOption } from "@/components/features/invoices/IssueInvoiceModal";

export const dynamic = "force-dynamic";

export default async function OwnerInvoicesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("invoices.owner");

  // Members feed the "Issue invoice" modal; only active members can be billed.
  const [{ invoices, summary }, memberList] = await Promise.all([
    ownerInvoices({ per_page: 200 }),
    listMembers({ status: "active", per_page: 200 }),
  ]);

  const memberOptions: InvoiceMemberOption[] = memberList.members.map((m) => ({
    id: String(m.user.id),
    name: m.user.name,
    avatar: m.user.avatar,
    planType: m.plan_type,
    seatNumber: m.seat_number,
    monthlyPrice: Number(m.monthly_price) || 0,
    package: m.package,
    packagePrice: Number(m.package_price) || 0,
  }));

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

      <div className="stack" style={{ gap: 18, marginTop: 8 }}>
        <div className="grid-stats grid-stats--3">
          <StatTile
            icon="receipt"
            amber={Number(summary.total_outstanding) > 0}
            label={t("summary.outstanding")}
            value={invoiceMoney(summary.total_outstanding)}
            foot={t("summary.outstandingFoot")}
          />
          <StatTile
            icon="alert"
            amber={Number(summary.total_overdue) > 0}
            label={t("summary.overdue")}
            value={invoiceMoney(summary.total_overdue)}
            foot={t("summary.overdueFoot")}
          />
          <StatTile
            icon="checkCircle"
            label={t("summary.paidThisMonth")}
            value={invoiceMoney(summary.paid_this_month)}
            foot={t("summary.paidThisMonthFoot")}
          />
        </div>

        <InvoicesTable invoices={invoices} members={memberOptions} />
      </div>
    </div>
  );
}
