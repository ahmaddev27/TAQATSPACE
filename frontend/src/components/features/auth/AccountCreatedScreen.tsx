import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";

export function AccountCreatedScreen() {
  const t = useTranslations("auth.created");
  return (
    <div className="reg-wrap" style={{ textAlign: "center", paddingTop: 64 }}>
      <div
        className="confirm-art"
        style={{ background: "var(--success-bg)", color: "var(--success)" }}
      >
        <Icon name="checkCircle" size={34} />
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
        className="row"
        style={{ gap: 12, justifyContent: "center", marginTop: 28 }}
      >
        <Link href="/login">
          <Button variant="primary">{t("goLogin")}</Button>
        </Link>
      </div>
    </div>
  );
}
