import type { Metadata } from "next";
import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import { NextIntlClientProvider, hasLocale } from "next-intl";
import { setRequestLocale } from "next-intl/server";
import { Cairo, Tajawal, IBM_Plex_Sans_Arabic, Inter } from "next/font/google";
import { routing } from "@/i18n/routing";
import { Providers } from "@/components/providers/Providers";
import { BrandingProvider } from "@/components/providers/BrandingProvider";
import { getBranding } from "@/lib/api/branding";
import type { Branding } from "@/lib/types";
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

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const isAr = locale === "ar";

  // Admin-set branding overrides the built-in title/description/favicon; we
  // fall back to the current defaults for anything unset. Never break metadata
  // generation on a fetch error.
  const branding: Branding = await getBranding().catch(() => ({}));

  const defaultTitle = isAr
    ? "طاقات سبيس — مساحات العمل المشتركة في غزة"
    : "TAQAT.space — Coworking spaces across Gaza";
  const defaultDescription = isAr
    ? "اكتشف واحجز مساحات العمل المشتركة في قطاع غزة — منصّة تجمع المستقلين وأصحاب المساحات."
    : "Discover and book coworking spaces across the Gaza Strip — connecting freelancers with workspace owners.";

  const siteName = branding.site_name?.trim() || (isAr ? "طاقات سبيس" : "TAQAT.space");
  const title = branding.meta_title?.trim() || defaultTitle;
  const description = branding.meta_description?.trim() || defaultDescription;
  const favicon = branding.faviconUrl?.trim() || "/icon.svg";

  return {
    metadataBase: new URL("https://taqat.space"),
    applicationName: siteName,
    title: {
      default: title,
      template: `%s · ${siteName}`,
    },
    description,
    icons: { icon: favicon },
    openGraph: {
      type: "website",
      siteName,
      title,
      description,
      url: `https://taqat.space/${locale}`,
      locale: isAr ? "ar_PS" : "en_US",
    },
    twitter: { card: "summary_large_image", title, description },
    alternates: {
      canonical: `https://taqat.space/${locale}`,
      languages: { ar: "/ar", en: "/en" },
    },
  };
}

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
  // [data-theme] up-front — no flash. When the user has NOT chosen a theme (no
  // cookie), a tiny pre-paint script (below) falls back to the device's
  // prefers-color-scheme, so the app honours the OS dark/light setting.
  const themeCookie = (await cookies()).get("taqat_theme")?.value;
  const theme = themeCookie === "dark" ? "dark" : undefined;
  const themeChosen = themeCookie === "dark" || themeCookie === "light";

  // Admin-set branding, fetched once on the server. The light/dark logo URLs
  // are exposed to client components via BrandingProvider so every brand spot
  // (sidebar, public header/footer, auth) renders the same logo — theme-aware,
  // no prop-drilling, no flash. Falls back to {} (built-in branding) on error.
  const branding: Branding = await getBranding().catch(() => ({}));

  return (
    <html
      lang={locale}
      dir={dir}
      data-theme={theme}
      className={`${cairo.variable} ${tajawal.variable} ${ibmArabic.variable} ${inter.variable} h-full antialiased`}
      suppressHydrationWarning
    >
      <body className="min-h-full">
        {/* When the user hasn't picked a theme, honour the device's dark/light
            setting before paint (no flash). An explicit choice (cookie) wins. */}
        {!themeChosen && (
          <script
            dangerouslySetInnerHTML={{
              __html:
                "(function(){try{if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.documentElement.setAttribute('data-theme','dark')}}catch(e){}})();",
            }}
          />
        )}
        <NextIntlClientProvider>
          <BrandingProvider branding={branding}>
            <Providers>{children}</Providers>
          </BrandingProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
