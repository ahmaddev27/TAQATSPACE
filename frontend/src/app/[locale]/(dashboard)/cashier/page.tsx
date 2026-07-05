import { setRequestLocale, getTranslations } from "next-intl/server";
import { Icon } from "@/components/ui/Icon";

export const dynamic = "force-dynamic";

/**
 * Placeholder cashier dashboard. The real POS terminal ships in a later slice;
 * for now the freshly-onboarded cashier lands on a friendly "being set up" card.
 */
export default async function CashierHomePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("cashier.dashboard");

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      <div
        className="card card-pad stack"
        style={{ gap: 12, alignItems: "center", textAlign: "center" }}
      >
        <span className="st-ico" style={{ width: 48, height: 48 }}>
          <Icon name="receipt" size={22} />
        </span>
        <h3 className="h3">{t("comingSoonTitle")}</h3>
        <p className="muted" style={{ maxWidth: 420 }}>
          {t("comingSoonBody")}
        </p>
      </div>
    </div>
  );
}
