import type { Metadata } from "next";
import { getPublicDict } from "@/components/features/public/i18n";
import { FaqAccordion } from "@/components/features/public/FaqAccordion";

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

  return (
    <section className="container section">
      <div style={{ textAlign: "center", maxWidth: 560, margin: "0 auto 40px" }}>
        <span className="eyebrow">{f.eyebrow}</span>
        <h1 className="ed-h2" style={{ marginTop: 8 }}>
          {f.title}
        </h1>
        <p className="muted" style={{ marginTop: 10 }}>
          {f.subtitle}
        </p>
      </div>

      <FaqAccordion dict={dict} freelancer={f.freelancer} owner={f.owner} />
    </section>
  );
}
