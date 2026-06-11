import type { Metadata } from "next";
import { getPublicDict } from "@/components/features/public/i18n";
import { Reveal } from "@/components/ui/Reveal";
import { FaqAccordion } from "@/components/features/public/FaqAccordion";
import { getContent } from "@/lib/api/content";
import { cmsText } from "@/lib/cms";
import type { FaqContent } from "@/lib/types";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const dict = getPublicDict(locale);
  return {
    title: `${dict.faq.title} | TAQAT.space`,
    description: dict.faq.subtitle,
  };
}

export default async function FaqPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const dict = getPublicDict(locale);
  const f = dict.faq;

  // Admin-managed FAQ overrides the default role-segmented list when present.
  const faq: FaqContent = await getContent("faq").catch(() => ({}));
  const cmsItems = (faq.items ?? [])
    .map((item) => ({ q: cmsText(item.question, locale), a: cmsText(item.answer, locale) }))
    .filter((item) => item.q && item.a);

  return (
    <section className="container section">
      <Reveal>
        <div style={{ textAlign: "center", maxWidth: 560, margin: "0 auto 40px" }}>
          <span className="eyebrow">{f.eyebrow}</span>
          <h1 className="ed-h2" style={{ marginTop: 8 }}>
            {f.title}
          </h1>
          <p className="muted" style={{ marginTop: 10 }}>
            {f.subtitle}
          </p>
        </div>
      </Reveal>

      {cmsItems.length > 0 ? (
        <FaqAccordion dict={dict} flat={cmsItems} />
      ) : (
        <FaqAccordion dict={dict} freelancer={f.freelancer} owner={f.owner} />
      )}
    </section>
  );
}
