import { getTranslations, getLocale } from "next-intl/server";
import { listSubscriptions } from "@/lib/api/subscriptions";
import { Icon } from "@/components/ui/Icon";
import { StatusBadge } from "@/components/ui/Badge";
import { CancelSubscriptionButton } from "@/components/features/freelancer/CancelSubscriptionButton";
import { AvailablePlansGrid } from "@/components/features/freelancer/AvailablePlansGrid";
import { SubscriptionHistoryTable } from "@/components/features/freelancer/SubscriptionHistoryTable";
import { NoSubscriptionPanel } from "@/components/features/freelancer/NoSubscriptionPanel";
import { formatDate, formatMoney } from "@/components/features/freelancer/format";

export default async function FreelancerSubscriptionPage() {
  const [subscriptions, t, tCommon, locale] = await Promise.all([
    listSubscriptions(),
    getTranslations("freelancer.subscription"),
    getTranslations("common"),
    getLocale(),
  ]);

  const currency = tCommon("currency");
  const active = subscriptions.find((s) => s.status === "active") ?? null;

  return (
    <div className="stack" style={{ gap: 24 }}>
      <div className="stack" style={{ gap: 4 }}>
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      {active ? (
        <div className="card card-pad">
          <div className="between wrap" style={{ gap: 16 }}>
            <div className="row" style={{ gap: 16 }}>
              <span
                className="st-ico amber"
                style={{ width: 52, height: 52, borderRadius: "var(--r-tile)" }}
              >
                <Icon name="card" size={24} />
              </span>
              <div>
                <div className="h3">{tCommon(`planType.${active.plan_type}`)}</div>
                <div className="muted">
                  {active.workspace
                    ? `${active.workspace.name} · ${active.workspace.city}`
                    : t("activeTitle")}
                </div>
              </div>
            </div>
            <StatusBadge status={active.status} locale={locale} />
          </div>

          <div className="divider" style={{ margin: "20px 0" }} />

          <div className="dash-3col">
            <Detail
              label={t("price")}
              value={`${formatMoney(active.monthly_price, currency)} ${t("perMonth")}`}
            />
            <Detail label={t("startDate")} value={formatDate(active.start_date)} />
            <Detail label={t("renewsOn")} value={formatDate(active.end_date)} />
            <Detail
              label={t("seat")}
              value={active.seat?.seat_number ?? t("noSeat")}
            />
          </div>

          <div className="row wrap" style={{ gap: 10, marginTop: 20 }}>
            <CancelSubscriptionButton
              subscriptionId={active.id}
              workspaceName={active.workspace?.name ?? ""}
              endDate={active.end_date ? formatDate(active.end_date) : null}
            />
          </div>
        </div>
      ) : (
        <NoSubscriptionPanel pendingRequests={0} />
      )}

      <h3 className="h3" style={{ marginTop: 6 }}>
        {locale === "ar" ? "الخطط المتاحة" : "Available plans"}
      </h3>
      <AvailablePlansGrid currency={currency} />

      <h3 className="h3" style={{ marginTop: 6 }}>
        {t("historyTitle")}
      </h3>
      <SubscriptionHistoryTable
        subscriptions={subscriptions}
        locale={locale}
        currency={currency}
      />
    </div>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
        {label}
      </div>
      <div
        className="tnum"
        style={{ fontWeight: 600, fontSize: "var(--fs-lg)", marginTop: 4 }}
      >
        {value}
      </div>
    </div>
  );
}
