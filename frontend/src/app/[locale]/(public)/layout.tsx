import type { ReactNode } from "react";
import { PublicHeader } from "@/components/features/public/PublicHeader";
import { PublicFooter } from "@/components/features/public/PublicFooter";
import { getPublicDict } from "@/components/features/public/i18n";

export default async function PublicLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const dict = getPublicDict(locale);

  return (
    <div className="pub">
      <PublicHeader dict={dict} />
      {children}
      <PublicFooter dict={dict} />
    </div>
  );
}
