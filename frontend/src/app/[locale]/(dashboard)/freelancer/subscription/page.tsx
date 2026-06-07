import { getTranslations, getLocale, setRequestLocale } from "next-intl/server";
import { listSubscriptions } from "@/lib/api/subscriptions";
import { Icon } from "@/components/ui/Icon";
import { StatusBadge } from "@/components/ui/Badge";
import { CancelSubscriptionButton } from "@/components/features/freelancer/CancelSubscriptionButton";
import { SubscriptionHistoryTable } from "@/components/features/freelancer/SubscriptionHistoryTable";
import { NoSubscriptionPanel } from "@/components/features/freelancer/NoSubscriptionPanel";
import { formatDate, formatMoney } from "@/components/features/freelancer/format";

export const dynamic = "force-dynamic";

export default async function FreelancerSubscriptionPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale: routeLocale } = await params;
  setRequestLocale(routeLocale);

  const [subscriptions, t, tCommon, locale] = await Promise.all([
    listSubscriptions(),
    getTranslations("freelancer.subscription"),
    getTranslations("common"),
    getLocale(),
  ]);

  const currency = tCommon("currency");
  const active = subscriptions.find((s) => s.status === "active") ?? null;
  const seatType = active?.seat?.type;

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
        {active ? (
          <div className="card card-pad">
            <div className="between wrap" style={{ gap: 16 }}>
              <div className="row" style={{ gap: 16 }}>
                <span
                  className="st-ico"
                  style={{
                    width: 52,
                    height: 52,
                    borderRadius: "var(--r-tile)",
                  }}
                >
                  <Icon name="card" size={24} />
                </span>
                <div>
                  <div className="h3">
                    {seatType ? tCommon(`seatType.${seatType}`) : t("activeTitle")}
                  </div>
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
                label={t("seatTypeLabel")}
                value={seatType ? tCommon(`seatType.${seatType}`) : t("noSeat")}
              />
              <Detail
                label={t("price")}
                value={`${formatMoney(active.monthly_price, currency)} ${t("perMonth")}`}
                numeric
              />
              <Detail
                label={t("seat")}
                value={active.seat?.seat_number ?? t("noSeat")}
                numeric
              />
              <Detail
                label={t("startDate")}
                value={formatDate(active.start_date)}
                numeric
              />
              <Detail
                label={t("renewsOn")}
                value={formatDate(active.end_date)}
                numeric
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

        <div className="stack" style={{ gap: 14 }}>
          <h3 className="h3">{t("historyTitle")}</h3>
          <SubscriptionHistoryTable
            subscriptions={subscriptions}
            locale={locale}
            currency={currency}
          />
        </div>
      </div>
    </div>
  );
}

function Detail({
  label,
  value,
  numeric,
}: {
  label: string;
  value: string;
  numeric?: boolean;
}) {
  return (
    <div>
      <div className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
        {label}
      </div>
      <div
        className={numeric ? "tnum" : undefined}
        style={{ fontWeight: 600, fontSize: "var(--fs-lg)", marginTop: 4 }}
      >
        {value}
      </div>
    </div>
  );
}
