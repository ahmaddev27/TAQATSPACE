import type { Metadata } from "next";
import { Icon, type IconName } from "@/components/ui/Icon";
import { getPublicDict } from "@/components/features/public/i18n";
import { ContactForm } from "@/components/features/public/ContactForm";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const dict = getPublicDict(locale);
  return {
    title: `${dict.contact.eyebrow} | TAQAT.space`,
    description: dict.contact.subtitle,
  };
}

export default async function ContactPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const dict = getPublicDict(locale);
  const t = dict.contact;

  const info: Array<{ ico: IconName; label: string; value: string }> = [
    { ico: "mail", label: t.emailLabel, value: "hello@taqat.space" },
    { ico: "phone", label: t.phoneLabel, value: "+970 8 000 0000" },
    { ico: "pin", label: t.addressLabel, value: t.addressValue },
  ];

  return (
    <section className="container section">
      <div className="why-grid" style={{ alignItems: "start" }}>
        <div className="why-copy">
          <span className="eyebrow">{t.eyebrow}</span>
          <h1 className="ed-h2" style={{ marginTop: 8 }}>
            {t.title}
          </h1>
          <p className="muted" style={{ fontSize: "var(--fs-lg)", lineHeight: 1.8, marginTop: 14 }}>
            {t.subtitle}
          </p>

          <div className="stack" style={{ gap: 16, marginTop: 28 }}>
            <h3 className="h3">{t.infoTitle}</h3>
            {info.map((item) => (
              <div key={item.label} className="row" style={{ gap: 12 }}>
                <span className="cap-ico" style={{ width: 42, height: 42 }}>
                  <Icon name={item.ico} size={18} />
                </span>
                <div>
                  <div className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
                    {item.label}
                  </div>
                  <div style={{ fontWeight: 600 }} className="ltr">
                    {item.value}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        <ContactForm dict={dict} />
      </div>
    </section>
  );
}
