import type { Metadata } from "next";
import { Link } from "@/i18n/navigation";
import { Icon, type IconName } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";
import { ImgPlaceholder } from "@/components/ui/ImgPlaceholder";
import { getPublicDict } from "@/components/features/public/i18n";
import { getContent } from "@/lib/api/content";
import { cmsText } from "@/lib/cms";
import type { AboutContent } from "@/lib/types";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const dict = getPublicDict(locale);
  return {
    title: `${dict.about.eyebrow} | TAQAT.space`,
    description: dict.about.lead,
  };
}

export default async function AboutPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const dict = getPublicDict(locale);
  const a = dict.about;

  // Admin-managed About content overrides the lead and adds extra sections.
  const about: AboutContent = await getContent("about").catch(() => ({}));
  const lead = cmsText(about.lead, locale, a.lead);
  const aboutSections = (about.sections ?? [])
    .map((s) => ({ heading: cmsText(s.heading, locale), body: cmsText(s.body, locale) }))
    .filter((s) => s.heading || s.body);

  const values: Array<{ ico: IconName; title: string; body: string }> = [
    { ico: "shield", title: a.value1, body: a.value1d },
    { ico: "users", title: a.value2, body: a.value2d },
    { ico: "checkCircle", title: a.value3, body: a.value3d },
    { ico: "zap", title: a.value4, body: a.value4d },
  ];
  const stats = [
    { n: "+128", l: a.statSpaces },
    { n: "3,400", l: a.statMembers },
    { n: "9", l: a.statCities },
  ];

  return (
    <>
      <section className="container section">
        <div className="why-grid">
          <div className="why-copy">
            <span className="eyebrow">{a.eyebrow}</span>
            <h1 className="ed-title" style={{ fontSize: "clamp(2.2rem, 4vw, 3.2rem)" }}>
              {a.title1} <span className="hl">{a.title2}</span>
            </h1>
            <p className="muted" style={{ fontSize: "var(--fs-lg)", lineHeight: 1.8, marginTop: 16 }}>
              {lead}
            </p>
            <div className="ed-stats" style={{ gridTemplateColumns: "repeat(3,auto)" }}>
              {stats.map((s) => (
                <div key={s.l} className="ed-stat">
                  <div className="ed-stat-n tnum">{s.n}</div>
                  <div className="ed-stat-l">{s.l}</div>
                </div>
              ))}
            </div>
          </div>
          <div className="why-collage">
            <ImgPlaceholder label={a.eyebrow} color="#cfe0ee" h={300} radius="var(--r-2xl)" className="why-img-1" />
            <ImgPlaceholder label="" color="#e7ddd2" h={190} radius="var(--r-2xl)" className="why-img-2" />
            <div className="why-blob">
              <Icon name="bulb" size={22} />
              <div className="ed-badge-n tnum" style={{ marginTop: 6 }}>
                TAQAT
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="caps-band">
        <div className="container">
          <div className="caps-grid" style={{ gridTemplateColumns: "repeat(2,1fr)" }}>
            <div className="cap-card">
              <span className="cap-ico">
                <Icon name="flag" size={22} />
              </span>
              <h3 className="h3" style={{ marginTop: 18 }}>
                {a.missionTitle}
              </h3>
              <p className="muted" style={{ marginTop: 8, lineHeight: 1.7 }}>
                {a.missionBody}
              </p>
            </div>
            <div className="cap-card">
              <span className="cap-ico">
                <Icon name="eye" size={22} />
              </span>
              <h3 className="h3" style={{ marginTop: 18 }}>
                {a.visionTitle}
              </h3>
              <p className="muted" style={{ marginTop: 8, lineHeight: 1.7 }}>
                {a.visionBody}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section className="container section">
        <div className="caps-head" style={{ maxWidth: 560, marginBottom: 32 }}>
          <h2 className="ed-h2">{a.valuesTitle}</h2>
        </div>
        <div className="caps-grid">
          {values.map((v) => (
            <div key={v.title} className="cap-card">
              <span className="cap-ico">
                <Icon name={v.ico} size={22} />
              </span>
              <h3 className="h3" style={{ marginTop: 18 }}>
                {v.title}
              </h3>
              <p className="muted" style={{ marginTop: 8, lineHeight: 1.7 }}>
                {v.body}
              </p>
            </div>
          ))}
        </div>
      </section>

      {aboutSections.length > 0 && (
        <section className="container section">
          <div className="stack" style={{ gap: 28, maxWidth: 760, margin: "0 auto" }}>
            {aboutSections.map((s, i) => (
              <div key={i}>
                {s.heading && (
                  <h2 className="ed-h2" style={{ marginBottom: 10 }}>
                    {s.heading}
                  </h2>
                )}
                {s.body && (
                  <p className="muted" style={{ lineHeight: 1.8, whiteSpace: "pre-line" }}>
                    {s.body}
                  </p>
                )}
              </div>
            ))}
          </div>
        </section>
      )}

      <section className="container">
        <div className="cta-band">
          <div className="cta-bulb">
            <Icon name="bulb" size={30} />
          </div>
          <h2 className="h1" style={{ maxWidth: 600, color: "#fff" }}>
            {a.ctaTitle}
          </h2>
          <p style={{ maxWidth: 480, opacity: 0.85 }}>{a.ctaBody}</p>
          <div className="row wrap" style={{ gap: 12, justifyContent: "center", marginTop: 8 }}>
            <Link href="/register/freelancer">
              <Button variant="accent" size="lg">
                {dict.home.heroCta1}
              </Button>
            </Link>
            <Link href="/register/workspace">
              <Button variant="secondary" size="lg">
                {dict.home.heroCta2}
              </Button>
            </Link>
          </div>
        </div>
      </section>
    </>
  );
}
