import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { TileLogo } from "@/components/layout/TileLogo";
import { Avatar } from "@/components/ui/Avatar";
import { LoginForm } from "@/components/features/auth/LoginForm";

export default async function LoginPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ loggedout?: string; sso_error?: string }>;
}) {
  const { locale } = await params;
  const { loggedout, sso_error } = await searchParams;
  const t = await getTranslations({ locale, namespace: "auth.login" });

  return (
    <div className="auth-wrap">
      <aside className="auth-art">
        <Link href="/" className="logo" style={{ cursor: "pointer" }}>
          <span className="logo-word" style={{ color: "#fff" }}>
            TAQAT
          </span>
        </Link>

        <div>
          <div style={{ marginBottom: 24 }}>
            <TileLogo size={46} />
          </div>
          <p className="auth-quote">{t("quote")}</p>
          <div className="row" style={{ gap: 10, marginTop: 20 }}>
            <Avatar initial={t("quoteName").charAt(0)} round />
            <div>
              <div style={{ fontWeight: 600 }}>{t("quoteName")}</div>
              <div style={{ opacity: 0.7, fontSize: "var(--fs-sm)" }}>
                {t("quoteRole")}
              </div>
            </div>
          </div>
        </div>

        <div style={{ opacity: 0.6, fontSize: "var(--fs-sm)" }} className="ltr">
          taqat.space
        </div>
      </aside>

      <div className="auth-form-side">
        <LoginForm loggedOut={loggedout === "1"} ssoError={sso_error === "1"} />
      </div>
    </div>
  );
}
