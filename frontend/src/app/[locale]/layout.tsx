import type { Metadata } from "next";
import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import { NextIntlClientProvider, hasLocale } from "next-intl";
import { setRequestLocale } from "next-intl/server";
import { Cairo, Tajawal, IBM_Plex_Sans_Arabic, Inter } from "next/font/google";
import { routing } from "@/i18n/routing";
import { Providers } from "@/components/providers/Providers";
import "../globals.css";

// Arabic families — expose Cairo as the primary --font-ar.
const cairo = Cairo({
  subsets: ["arabic", "latin"],
  variable: "--font-ar",
  display: "swap",
});
const tajawal = Tajawal({
  subsets: ["arabic", "latin"],
  weight: ["400", "500", "700"],
  variable: "--font-tajawal",
  display: "swap",
});
const ibmArabic = IBM_Plex_Sans_Arabic({
  subsets: ["arabic", "latin"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-ibm-ar",
  display: "swap",
});

// Latin / numerals.
const inter = Inter({
  subsets: ["latin"],
  variable: "--font-lt",
  display: "swap",
});

export const metadata: Metadata = {
  title: "TAQAT.space",
  description: "Coworking-space marketplace",
};

export function generateStaticParams() {
  return routing.locales.map((locale) => ({ locale }));
}

export default async function LocaleLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  if (!hasLocale(routing.locales, locale)) {
    notFound();
  }

  setRequestLocale(locale);
  const dir = locale === "ar" ? "rtl" : "ltr";

  // Theme is persisted in a cookie so the server renders the correct
  // [data-theme] up-front — no flash, and no inline <script> in the tree.
  const theme =
    (await cookies()).get("taqat_theme")?.value === "dark" ? "dark" : undefined;

  return (
    <html
      lang={locale}
      dir={dir}
      data-theme={theme}
      className={`${cairo.variable} ${tajawal.variable} ${ibmArabic.variable} ${inter.variable} h-full antialiased`}
      suppressHydrationWarning
    >
      <body className="min-h-full">
        <NextIntlClientProvider>
          <Providers>{children}</Providers>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
