import { getTranslations, setRequestLocale } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { getAdminStats } from "@/lib/api/admin";
import { StatTile } from "@/components/ui/StatTile";
import { Icon, type IconName } from "@/components/ui/Icon";
import { invoiceMoney } from "@/components/features/invoices/format";

export const dynamic = "force-dynamic";

interface QuickLink {
  href: string;
  icon: IconName;
  label: string;
}

export default async function AdminDashboardPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("admin.dashboard");

  const stats = await getAdminStats();

  const quickLinks: QuickLink[] = [
    { href: "/admin/workspaces", icon: "building", label: t("qaWorkspaces") },
    { href: "/admin/users", icon: "users", label: t("qaUsers") },
    { href: "/admin/subscriptions", icon: "card", label: t("qaSubscriptions") },
    { href: "/admin/invoices", icon: "receipt", label: t("qaInvoices") },
  ];

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

      <div className="stack" style={{ gap: 24, marginTop: 8 }}>
        {/* Platform counters */}
        <div className="grid-stats">
          <StatTile
            icon="building"
            label={t("workspaces")}
            value={stats.workspaces.total}
            foot={t("workspacesFoot", {
              active: stats.workspaces.active,
              pending: stats.workspaces.pending,
              suspended: stats.workspaces.suspended,
            })}
            amber={stats.workspaces.pending > 0}
          />
          <StatTile
            icon="users"
            label={t("users")}
            value={stats.users.total}
            foot={t("usersFoot", {
              owners: stats.users.owners,
              freelancers: stats.users.freelancers,
            })}
            amber={stats.users.pending > 0}
          />
          <StatTile
            icon="card"
            label={t("subscriptions")}
            value={stats.subscriptions.total}
            foot={t("subscriptionsFoot", { active: stats.subscriptions.active })}
          />
          <StatTile
            icon="receipt"
            label={t("invoices")}
            value={stats.invoices.total}
            foot={t("invoicesFoot", {
              paid: stats.invoices.paid,
              pending: stats.invoices.pending,
              overdue: stats.invoices.overdue,
            })}
            amber={stats.invoices.overdue > 0}
          />
        </div>

        {/* Tracked revenue — admin bookkeeping, not gateway money */}
        <div className="card card-pad stack" style={{ gap: 16 }}>
          <div>
            <h3 className="h3">{t("revenueTitle")}</h3>
            <p
              className="muted-3"
              style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}
            >
              {t("revenueSubtitle")}
            </p>
          </div>
          <div
            className="grid-stats"
            style={{ gridTemplateColumns: "repeat(2, 1fr)" }}
          >
            <StatTile
              icon="wallet"
              label={t("revenuePaid")}
              value={invoiceMoney(stats.revenue.paid)}
              foot={t("revenuePaidFoot")}
            />
            <StatTile
              icon="alert"
              amber={Number(stats.revenue.outstanding) > 0}
              label={t("revenueOutstanding")}
              value={invoiceMoney(stats.revenue.outstanding)}
              foot={t("revenueOutstandingFoot")}
            />
          </div>
        </div>

        {/* Quick links to the management pages */}
        <div className="card card-pad stack" style={{ gap: 12 }}>
          <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
            {t("quickLinks")}
          </h3>
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(4, 1fr)",
              gap: 10,
            }}
          >
            {quickLinks.map((q) => (
              <Link
                key={q.href}
                href={q.href}
                className="card card-pad row"
                style={{
                  gap: 10,
                  alignItems: "center",
                  textDecoration: "none",
                  color: "var(--text)",
                }}
              >
                <span className="st-ico">
                  <Icon name={q.icon} />
                </span>
                <span style={{ fontSize: "var(--fs-sm)", fontWeight: 500 }}>
                  {q.label}
                </span>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
