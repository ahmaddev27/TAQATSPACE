import type { Metadata } from "next";
import { Link } from "@/i18n/navigation";
import { Icon, type IconName } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";
import { Reveal } from "@/components/ui/Reveal";
import { getPublicDict } from "@/components/features/public/i18n";
import { getAboutContent } from "@/lib/api/about";
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

  // Admin-managed About content drives every visible string + image, falling
  // back to the i18n dictionary (and striped placeholders) for anything unset.
  const about: AboutContent = await getAboutContent().catch(() => ({}));

  const eyebrow = cmsText(about.eyebrow, locale, a.eyebrow);
  const title1 = cmsText(about.title1, locale, a.title1);
  const title2 = cmsText(about.title2, locale, a.title2);
  const lead = cmsText(about.lead, locale, a.lead);

  const missionTitle = cmsText(about.mission?.title, locale, a.missionTitle);
  const missionBody = cmsText(about.mission?.body, locale, a.missionBody);
  const visionTitle = cmsText(about.vision?.title, locale, a.visionTitle);
  const visionBody = cmsText(about.vision?.body, locale, a.visionBody);
  const valuesTitle = cmsText(about.valuesTitle, locale, a.valuesTitle);
  const ctaTitle = cmsText(about.cta?.title, locale, a.ctaTitle);
  const ctaBody = cmsText(about.cta?.body, locale, a.ctaBody);

  const aboutSections = (about.sections ?? [])
    .map((s) => ({
      heading: cmsText(s.heading, locale),
      body: cmsText(s.body, locale),
      imageUrl: s.imageUrl,
    }))
    .filter((s) => s.heading || s.body || s.imageUrl);

  // Values keep their FIXED icons (by index); copy comes from the CMS.
  const values: Array<{ ico: IconName; title: string; body: string }> = [
    { ico: "shield", title: cmsText(about.values?.[0]?.title, locale, a.value1), body: cmsText(about.values?.[0]?.body, locale, a.value1d) },
    { ico: "users", title: cmsText(about.values?.[1]?.title, locale, a.value2), body: cmsText(about.values?.[1]?.body, locale, a.value2d) },
    { ico: "checkCircle", title: cmsText(about.values?.[2]?.title, locale, a.value3), body: cmsText(about.values?.[2]?.body, locale, a.value3d) },
    { ico: "zap", title: cmsText(about.values?.[3]?.title, locale, a.value4), body: cmsText(about.values?.[3]?.body, locale, a.value4d) },
  ];
  const stats = [
    { n: about.stats?.[0]?.value || "+128", l: cmsText(about.stats?.[0]?.label, locale, a.statSpaces) },
    { n: about.stats?.[1]?.value || "3,400", l: cmsText(about.stats?.[1]?.label, locale, a.statMembers) },
    { n: about.stats?.[2]?.value || "9", l: cmsText(about.stats?.[2]?.label, locale, a.statCities) },
  ];

  return (
    <>
      <section className="container section">
        <Reveal>
        <div className="why-grid">
          <div className="why-copy">
            <span className="eyebrow">{eyebrow}</span>
            <h1 className="ed-title" style={{ fontSize: "clamp(2.2rem, 4vw, 3.2rem)" }}>
              {title1} <span className="hl">{title2}</span>
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
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              className="why-img-1"
              src={about.imageUrl || "/images/workspaces/cowork-open.jpg"}
              alt={title1 || eyebrow}
              style={{ height: 300, borderRadius: "var(--r-2xl)", objectFit: "cover" }}
            />
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              className="why-img-2"
              src={about.imageSecondaryUrl || "/images/workspaces/cowork-lounge.jpg"}
              alt={title2 || eyebrow}
              style={{ height: 190, borderRadius: "var(--r-2xl)", objectFit: "cover" }}
            />
            <div className="why-blob">
              <Icon name="bulb" size={22} />
              <div className="ed-badge-n tnum" style={{ marginTop: 6 }}>
                TAQAT
              </div>
            </div>
          </div>
        </div>
        </Reveal>
      </section>

      <section className="caps-band">
        <div className="container">
          <Reveal>
          <div className="caps-grid" style={{ gridTemplateColumns: "repeat(2,1fr)" }}>
            <div className="cap-card">
              <span className="cap-ico">
                <Icon name="flag" size={22} />
              </span>
              <h3 className="h3" style={{ marginTop: 18 }}>
                {missionTitle}
              </h3>
              <p className="muted" style={{ marginTop: 8, lineHeight: 1.7 }}>
                {missionBody}
              </p>
            </div>
            <div className="cap-card">
              <span className="cap-ico">
                <Icon name="eye" size={22} />
              </span>
              <h3 className="h3" style={{ marginTop: 18 }}>
                {visionTitle}
              </h3>
              <p className="muted" style={{ marginTop: 8, lineHeight: 1.7 }}>
                {visionBody}
              </p>
            </div>
          </div>
          </Reveal>
        </div>
      </section>

      <section className="container section">
        <Reveal>
          <div className="caps-head" style={{ maxWidth: 560, marginBottom: 32 }}>
            <h2 className="ed-h2">{valuesTitle}</h2>
          </div>
        </Reveal>
        <div className="caps-grid">
          {values.map((v, i) => (
            <Reveal as="div" index={i} key={v.title} className="cap-card">
              <span className="cap-ico">
                <Icon name={v.ico} size={22} />
              </span>
              <h3 className="h3" style={{ marginTop: 18 }}>
                {v.title}
              </h3>
              <p className="muted" style={{ marginTop: 8, lineHeight: 1.7 }}>
                {v.body}
              </p>
            </Reveal>
          ))}
        </div>
      </section>

      {aboutSections.length > 0 && (
        <section className="container section">
          <div className="stack" style={{ gap: 28, maxWidth: 760, margin: "0 auto" }}>
            {aboutSections.map((s, i) => (
              <Reveal as="div" index={i} key={i}>
                {s.imageUrl && (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={s.imageUrl}
                    alt={s.heading || ""}
                    style={{
                      width: "100%",
                      borderRadius: "var(--r-lg)",
                      objectFit: "cover",
                      display: "block",
                      marginBottom: 14,
                    }}
                  />
                )}
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
              </Reveal>
            ))}
          </div>
        </section>
      )}

      <section className="container">
        <Reveal>
        <div className="cta-band">
          <div className="cta-bulb">
            <Icon name="bulb" size={30} />
          </div>
          <h2 className="h1" style={{ maxWidth: 600, color: "#fff" }}>
            {ctaTitle}
          </h2>
          <p style={{ maxWidth: 480, opacity: 0.85 }}>{ctaBody}</p>
          <div className="row wrap" style={{ gap: 12, justifyContent: "center", marginTop: 8 }}>
            <Link href="/login">
              <Button variant="accent" size="lg">
                {dict.home.heroCta1}
              </Button>
            </Link>
            <Link href="/login">
              <Button variant="secondary" size="lg">
                {dict.home.heroCta2}
              </Button>
            </Link>
          </div>
        </div>
        </Reveal>
      </section>
    </>
  );
}
