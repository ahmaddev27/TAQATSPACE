import { Link } from "@/i18n/navigation";
import { BrandLogo } from "@/components/layout/BrandLogo";
import { cmsText } from "@/lib/cms";
import type { SiteContent } from "@/lib/types";
import type { PublicDict } from "./i18n";

export interface PublicFooterProps {
  dict: PublicDict;
  site?: SiteContent;
  locale?: string;
}

/** Public marketing footer (ported from prototype `PublicFooter`). */
export function PublicFooter({ dict, site = {}, locale = "ar" }: PublicFooterProps) {
  const f = dict.footer;
  const social = Object.entries(site.social ?? {}).filter(([, url]) => !!url);
  const columns: Array<{ heading: string; links: Array<{ href: string; label: string }> }> = [
    {
      heading: f.platform,
      links: [
        { href: "/explore", label: f.explore },
        { href: "/about", label: f.about },
        { href: "/faq", label: f.how },
      ],
    },
    {
      heading: f.members,
      links: [
        { href: "/register/freelancer", label: f.regFree },
        { href: "/faq", label: f.faq },
        { href: "/contact", label: f.contact },
      ],
    },
    {
      heading: f.owners,
      links: [
        { href: "/register/workspace", label: f.listSpace },
        { href: "/login", label: f.login },
        { href: "/contact", label: f.contact },
      ],
    },
  ];

  return (
    <footer className="pub-footer">
      <div className="container pub-footer-in">
        <div className="pub-footer-brand">
          <Link href="/" className="logo" aria-label="TAQAT">
            <BrandLogo size={24} />
          </Link>
          <p className="muted" style={{ maxWidth: 260, marginTop: 14 }}>
            {f.tag}
          </p>
          {(site.contact_email || site.contact_phone || site.whatsapp || cmsText(site.address, locale) || social.length > 0) && (
            <div className="pf-contact">
              {cmsText(site.address, locale) && (
                <span className="muted-3">{cmsText(site.address, locale)}</span>
              )}
              {site.contact_email && (
                <a className="pf-link ltr" href={`mailto:${site.contact_email}`}>
                  {site.contact_email}
                </a>
              )}
              {site.contact_phone && (
                <a className="pf-link ltr" href={`tel:${site.contact_phone}`}>
                  {site.contact_phone}
                </a>
              )}
              {site.whatsapp && (
                <a
                  className="pf-link ltr"
                  href={`https://wa.me/${site.whatsapp.replace(/[^\d]/g, "")}`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  WhatsApp
                </a>
              )}
              {social.length > 0 && (
                <div className="pf-social">
                  {social.map(([name, url]) => (
                    <a
                      key={name}
                      className="pf-link ltr"
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
          )}
        </div>
        {columns.map((col) => (
          <div key={col.heading} className="pub-footer-col">
            <h4 className="pf-h">{col.heading}</h4>
            {col.links.map((l) => (
              <Link key={`${col.heading}-${l.href}-${l.label}`} href={l.href} className="pf-link">
                {l.label}
              </Link>
            ))}
          </div>
        ))}
      </div>
      <div className="container pub-footer-bottom">
        <span className="muted-3">{f.rights}</span>
        <span className="muted-3 ltr">taqat.space</span>
      </div>
    </footer>
  );
}
