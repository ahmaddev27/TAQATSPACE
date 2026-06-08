import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getAdminContent } from "@/lib/api/content";
import { getAdminBranding } from "@/lib/api/branding";
import { ContentManager } from "@/components/features/admin/ContentManager";
import type {
  AboutContent,
  Branding,
  FaqContent,
  HowItWorksContent,
  SiteContent,
} from "@/lib/types";

export const dynamic = "force-dynamic";

/** Fetch one admin blob, falling back to an empty object on any failure. */
async function loadContent<T>(key: Parameters<typeof getAdminContent>[0]): Promise<T> {
  try {
    return (await getAdminContent(key)) as T;
  } catch {
    return {} as T;
  }
}

export default async function AdminContentPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_content");
  const t = await getTranslations("admin.rm");

  // Each blob loads independently; a single CMS failure must not break the hub.
  const [site, faq, about, howItWorks, branding] = await Promise.all([
    loadContent<SiteContent>("site"),
    loadContent<FaqContent>("faq"),
    loadContent<AboutContent>("about"),
    loadContent<HowItWorksContent>("how_it_works"),
    getAdminBranding().catch(() => ({}) as Branding),
  ]);

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
      </div>

      <div style={{ marginTop: 16 }}>
        <ContentManager
          site={site}
          faq={faq}
          about={about}
          howItWorks={howItWorks}
          branding={branding}
        />
      </div>
    </div>
  );
}
