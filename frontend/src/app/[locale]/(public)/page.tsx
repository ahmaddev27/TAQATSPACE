import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { TileLogo } from "@/components/layout/TileLogo";
import { LanguageToggle } from "@/components/layout/LanguageToggle";
import { ThemeToggle } from "@/components/layout/ThemeToggle";
import { Button } from "@/components/ui/Button";

export default async function LandingPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "nav" });

  return (
    <main className="pub">
      <header className="pub-header">
        <div className="container pub-header-in">
          <Link href="/" className="logo">
            <span className="logo-word">TAQAT</span>
          </Link>
          <div className="row" style={{ gap: 8 }}>
            <LanguageToggle />
            <ThemeToggle />
          </div>
        </div>
      </header>

      <section
        className="container"
        style={{
          minHeight: "70vh",
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          justifyContent: "center",
          textAlign: "center",
          gap: 24,
          paddingBlock: 64,
        }}
      >
        <TileLogo size={64} />
        <h1 className="ed-title" style={{ maxWidth: 640 }}>
          TAQAT.space
        </h1>
        <p className="ed-lead">{/* P2 — full landing page in Phase 2 */}</p>
        <div className="row" style={{ gap: 12, flexWrap: "wrap", justifyContent: "center" }}>
          <Link href="/login">
            <Button variant="primary" size="lg">
              {t("login")}
            </Button>
          </Link>
          <Link href="/register/freelancer">
            <Button variant="secondary" size="lg">
              {t("signup")}
            </Button>
          </Link>
        </div>
      </section>
    </main>
  );
}
