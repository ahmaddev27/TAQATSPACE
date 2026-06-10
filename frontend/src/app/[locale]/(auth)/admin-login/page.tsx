import { getTranslations, setRequestLocale } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { BrandLogo } from "@/components/layout/BrandLogo";
import { AdminLoginForm } from "@/components/features/auth/AdminLoginForm";

/**
 * Un-advertised staff entrance. The public site is SSO-only; this direct-URL
 * page lets internal admins sign in with email/password. It is intentionally not
 * linked from any public CTA or navigation.
 */
export default async function AdminLoginPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations({ locale, namespace: "auth.adminLogin" });

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
            <BrandLogo size={46} forceDark />
          </div>
          <p className="auth-quote">{t("subtitle")}</p>
        </div>

        <div style={{ opacity: 0.6, fontSize: "var(--fs-sm)" }} className="ltr">
          taqat.space
        </div>
      </aside>

      <div className="auth-form-side">
        <AdminLoginForm />
      </div>
    </div>
  );
}
