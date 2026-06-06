import { Suspense, type ReactNode } from "react";
import { PublicHeader } from "@/components/features/public/PublicHeader";
import { PublicFooter } from "@/components/features/public/PublicFooter";
import { getPublicDict } from "@/components/features/public/i18n";
import { getContent } from "@/lib/api/content";
import { NavProgress } from "@/components/layout/NavProgress";

export default async function PublicLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const dict = getPublicDict(locale);
  // Admin-managed contact/social details for the footer (empty until set).
  const site = await getContent("site").catch(() => ({}));

  return (
    <div className="pub">
      <Suspense fallback={null}>
        <NavProgress />
      </Suspense>
      <PublicHeader dict={dict} />
      {children}
      <PublicFooter dict={dict} site={site} locale={locale} />
    </div>
  );
}
