"use client";

import { useLocale } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Button } from "@/components/ui/Button";
import { LanguageToggle } from "@/components/layout/LanguageToggle";
import { ThemeToggle } from "@/components/layout/ThemeToggle";
import { BrandLogo } from "@/components/layout/BrandLogo";
import { useAuth } from "@/components/providers/AuthProvider";
import { dashboardFor } from "@/lib/auth";
import type { PublicDict } from "./i18n";

export interface PublicHeaderProps {
  dict: PublicDict;
}

/** Sticky public marketing header (ported from prototype `PublicHeader`). */
export function PublicHeader({ dict }: PublicHeaderProps) {
  const nav = dict.nav;
  const locale = useLocale();
  const { isAuthenticated, isLoading, role } = useAuth();
  const links: Array<{ href: string; label: string }> = [
    { href: "/explore", label: nav.explore },
    { href: "/about", label: nav.about },
    { href: "/faq", label: nav.faq },
    { href: "/contact", label: nav.contact },
  ];

  return (
    <header className="pub-header">
      <div className="container pub-header-in">
        <Link href="/" className="logo" aria-label="TAQAT">
          <BrandLogo size={26} />
        </Link>
        <nav className="pub-nav">
          {links.map((l) => (
            <Link key={l.href} href={l.href} className="pub-nav-link">
              {l.label}
            </Link>
          ))}
        </nav>
        <div className="row" style={{ gap: 8 }}>
          <ThemeToggle />
          <LanguageToggle />
          {!isLoading && isAuthenticated ? (
            <Link href={dashboardFor(role)}>
              <Button variant="primary" size="sm" icon="grid">
                {locale === "ar" ? "لوحة التحكم" : "Dashboard"}
              </Button>
            </Link>
          ) : (
            <>
              <Link href="/login">
                <Button variant="ghost" size="sm">
                  {nav.login}
                </Button>
              </Link>
              <Link href="/register/freelancer">
                <Button variant="primary" size="sm">
                  {nav.getStarted}
                </Button>
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
