import type { Metadata } from "next";
import { Icon, type IconName } from "@/components/ui/Icon";
import { getPublicDict } from "@/components/features/public/i18n";
import { ContactForm } from "@/components/features/public/ContactForm";
import { getContent } from "@/lib/api/content";
import { cmsText } from "@/lib/cms";
import type { SiteContent } from "@/lib/types";

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

  // Admin-managed contact details override the static defaults when present.
  const site: SiteContent = await getContent("site").catch(() => ({}));
  const info: Array<{ ico: IconName; label: string; value: string }> = [
    { ico: "mail", label: t.emailLabel, value: site.contact_email || "hello@taqat.space" },
    { ico: "phone", label: t.phoneLabel, value: site.contact_phone || "+970 8 000 0000" },
    { ico: "pin", label: t.addressLabel, value: cmsText(site.address, locale, t.addressValue) },
  ];
  if (site.whatsapp) {
    info.push({ ico: "phone", label: "WhatsApp", value: site.whatsapp });
  }
  const social = Object.entries(site.social ?? {}).filter(([, url]) => !!url);

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
            {social.length > 0 && (
              <div className="row wrap" style={{ gap: 14, marginTop: 6 }}>
                {social.map(([name, url]) => (
                  <a
                    key={name}
                    className="link ltr"
                    href={url as string}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {name}
                  </a>
                ))}
              </div>
            )}
          </div>
        </div>

        <ContactForm dict={dict} />
      </div>
    </section>
  );
}
