import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";
import { StatusBadge } from "@/components/ui/Badge";

export function PendingReviewScreen() {
  const t = useTranslations("auth.pending");
  const locale = useLocale();
  return (
    <div className="reg-wrap" style={{ textAlign: "center", paddingTop: 64 }}>
      <div className="confirm-art">
        <Icon name="clock" size={34} />
      </div>
      <h1 className="h1" style={{ marginTop: 24 }}>
        {t("title")}
      </h1>
      <p
        className="muted"
        style={{ maxWidth: 440, margin: "12px auto 0", lineHeight: 1.7 }}
      >
        {t("body")}
      </p>
      <div
        className="card"
        style={{ maxWidth: 380, margin: "28px auto 0", padding: 18, textAlign: "start" }}
      >
        <div className="between">
          <span className="muted">{t("statusLabel")}</span>
          <StatusBadge status="pending" locale={locale} />
        </div>
      </div>
      <p
        className="muted-3"
        style={{ marginTop: 16, fontSize: "var(--fs-sm)" }}
      >
        {t("checkEmail")}
      </p>
      <div
        className="row"
        style={{ gap: 12, justifyContent: "center", marginTop: 28 }}
      >
        <Link href="/">
          <Button variant="secondary">{t("backHome")}</Button>
        </Link>
      </div>
    </div>
  );
}
