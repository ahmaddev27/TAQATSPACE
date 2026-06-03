import { Link } from "@/i18n/navigation";
import { TileLogo } from "@/components/layout/TileLogo";
import type { PublicDict } from "./i18n";

export interface PublicFooterProps {
  dict: PublicDict;
}

/** Public marketing footer (ported from prototype `PublicFooter`). */
export function PublicFooter({ dict }: PublicFooterProps) {
  const f = dict.footer;
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
            <TileLogo size={24} />
          </Link>
          <p className="muted" style={{ maxWidth: 260, marginTop: 14 }}>
            {f.tag}
          </p>
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
