import type { ReactNode } from "react";
import { Link } from "@/i18n/navigation";
import { Icon, type IconName } from "@/components/ui/Icon";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { CoverImage } from "@/components/ui/CoverImage";
import { listWorkspaces } from "@/lib/api/workspaces";
import { getLanding } from "@/lib/api/landing";
import { getContent } from "@/lib/api/content";
import type { HowItWorksContent, LandingContent, LocalizedText, Workspace } from "@/lib/types";
import { sanitizeSectionsOrder } from "@/lib/types";
import { getPublicDict } from "@/components/features/public/i18n";
import { WorkspaceCard } from "@/components/features/public/WorkspaceCard";
import { HeroSeatMini } from "@/components/features/public/HeroSeatMini";
import { Marquee } from "@/components/features/public/Marquee";

export const dynamic = "force-dynamic";

/** First non-empty string wins: CMS override for the active locale, else default. */
function pick(value: string | undefined, fallback: string): string {
  const trimmed = value?.trim();
  return trimmed ? trimmed : fallback;
}

/** Read the active-locale side of a CMS bilingual field. */
function loc(field: LocalizedText | undefined, locale: string): string | undefined {
  return locale === "en" ? field?.en : field?.ar;
}

/** A section is shown unless the CMS has explicitly disabled it. */
function isEnabled(enabled: boolean | undefined): boolean {
  return enabled !== false;
}

export default async function HomePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const dict = getPublicDict(locale);
  const h = dict.home;
  const c = dict.common;

  // A CMS failure must never break the landing page.
  const cms: LandingContent = await getLanding().catch(() => ({}));
  const sections = cms.sections ?? {};

  // Featured workspaces: prefer the admin-selected ids (in their chosen order),
  // otherwise fall back to top-rated discovery — same as before the CMS.
  const featuredIds = sections.featured?.workspaceIds ?? [];
  let featured: Workspace[] = [];
  try {
    if (featuredIds.length > 0) {
      const page = await listWorkspaces({ page: 1 });
      const byId = new Map(page.data.map((w) => [w.id, w]));
      featured = featuredIds
        .map((id) => byId.get(id))
        .filter((w): w is Workspace => w !== undefined);
    } else {
      const page = await listWorkspaces({ sort: "rating", page: 1 });
      featured = page.data.slice(0, 6);
    }
  } catch {
    featured = [];
  }

  // Stats: CMS list (value + localized label) when present, else the defaults.
  const cmsStats = cms.stats ?? [];
  const stats =
    cmsStats.length > 0
      ? cmsStats.map((s, i) => ({
          n: s.value ?? "",
          l: pick(loc(s.label, locale), ""),
          key: `cms-${i}`,
        }))
      : [
          { n: "+128", l: h.statSpaces, key: "statSpaces" },
          { n: "3,400", l: h.statFree, key: "statFree" },
          { n: "72%", l: h.statOcc, key: "statOcc" },
          { n: "24/7", l: h.statAlways, key: "statAlways" },
        ];

  const whys = [h.why1, h.why2, h.why3, h.why4];
  // Admin-managed "how it works" steps override the default capabilities grid.
  const hiw: HowItWorksContent = await getContent("how_it_works").catch(() => ({}));
  const CAP_ICONS: IconName[] = ["search", "calendar", "receipt", "grid", "wifi", "chart"];
  const cmsSteps = (hiw.steps ?? [])
    .map((s, i) => ({
      n: String(i + 1).padStart(2, "0"),
      ico: CAP_ICONS[i % CAP_ICONS.length],
      t: pick(loc(s.title, locale), ""),
      d: pick(loc(s.description, locale), ""),
    }))
    .filter((s) => s.t || s.d);
  const caps: Array<{ n: string; ico: IconName; t: string; d: string }> =
    cmsSteps.length > 0
      ? cmsSteps
      : [
          { n: "01", ico: "search", t: dict.caps["1"], d: dict.caps["1d"] },
          { n: "02", ico: "calendar", t: dict.caps["2"], d: dict.caps["2d"] },
          { n: "03", ico: "receipt", t: dict.caps["3"], d: dict.caps["3d"] },
          { n: "04", ico: "grid", t: dict.caps["4"], d: dict.caps["4d"] },
          { n: "05", ico: "wifi", t: dict.caps["5"], d: dict.caps["5d"] },
          { n: "06", ico: "chart", t: dict.caps["6"], d: dict.caps["6d"] },
        ];

  // Testimonials: CMS list when present, else the default trio.
  const cmsTestimonials = cms.testimonials ?? [];
  const testimonials =
    cmsTestimonials.length > 0
      ? cmsTestimonials.map((m, i) => {
          const name = m.name ?? "";
          return {
            text: pick(loc(m.text, locale), ""),
            name,
            role: pick(loc(m.role, locale), ""),
            avatar: name.trim().charAt(0) || "•",
            key: `cms-${i}`,
          };
        })
      : [
          { text: dict.tstm["1"], name: dict.tstm["1n"], role: dict.tstm["1r"], avatar: dict.tstm["1a"], key: "t1" },
          { text: dict.tstm["2"], name: dict.tstm["2n"], role: dict.tstm["2r"], avatar: dict.tstm["2a"], key: "t2" },
          { text: dict.tstm["3"], name: dict.tstm["3n"], role: dict.tstm["3r"], avatar: dict.tstm["3a"], key: "t3" },
        ];

  // Hero copy: CMS override per field, else the dictionary default.
  const heroTitle = pick(loc(cms.hero?.title, locale), h.heroT1);
  const heroHighlight = pick(loc(cms.hero?.highlight, locale), h.heroT2);
  const heroSub = pick(loc(cms.hero?.subtitle, locale), h.heroSub);
  const heroCta1 = pick(loc(cms.hero?.ctaPrimary, locale), h.heroCta1);
  const heroCta2 = pick(loc(cms.hero?.ctaSecondary, locale), h.heroCta2);

  // Section headings.
  const whyTitle = pick(loc(sections.why?.title, locale), h.whyTitle1);
  const whyHighlight = pick(loc(sections.why?.highlight, locale), h.whyTitle2);
  const whySub = pick(loc(sections.why?.subtitle, locale), h.whySub);
  const capsTitle = pick(loc(sections.capabilities?.title, locale), h.capsTitle1);
  const capsHighlight = pick(loc(sections.capabilities?.subtitle, locale), h.capsTitle2);
  const featuredTitle = pick(loc(sections.featured?.title, locale), h.featured);
  const featuredSub = pick(loc(sections.featured?.subtitle, locale), h.featuredEyebrow);
  const tstmTitle = pick(loc(sections.testimonials?.title, locale), h.tstmTitle1);

  // Section illustrations: the admin-chosen image (resolved URL) when set,
  // otherwise the built-in default — so an unset image renders exactly as before.
  const HERO_IMG_DEFAULT = "/images/workspaces/placeholder-1.svg";
  const HERO_SECONDARY_DEFAULT = "/images/workspaces/placeholder-3.svg";
  const WHY_IMG_DEFAULT = "/images/workspaces/placeholder-2.svg";
  const WHY_SECONDARY_DEFAULT = "/images/workspaces/placeholder-1.svg";
  const heroImage = pick(cms.hero?.imageUrl, HERO_IMG_DEFAULT);
  const heroImageSecondary = pick(cms.hero?.imageSecondaryUrl, HERO_SECONDARY_DEFAULT);
  const whyImage = pick(sections.why?.imageUrl, WHY_IMG_DEFAULT);
  const whyImageSecondary = pick(sections.why?.imageSecondaryUrl, WHY_SECONDARY_DEFAULT);

  const showWhy = isEnabled(sections.why?.enabled);
  const showCapabilities = isEnabled(sections.capabilities?.enabled);
  const showFeatured = isEnabled(sections.featured?.enabled) && featured.length > 0;
  const showTestimonials = isEnabled(sections.testimonials?.enabled);

  // The reorderable sections, keyed so they can render in the admin-chosen
  // order. Each entry is the same JSX as before; only the surrounding ordering
  // changed. `null` when the section is disabled (or has nothing to show).
  const sectionNodes: Record<string, ReactNode> = {
    why: showWhy ? (
      <section key="why" className="container section" id="about">
        <div className="why-grid">
          <div className="why-collage">
            <CoverImage
              src={whyImage}
              fallbackSrc={WHY_IMG_DEFAULT}
              alt={h.imgInside}
              h="clamp(190px, 36vw, 300px)"
              radius="var(--r-2xl)"
              className="why-img-1"
            />
            <CoverImage
              src={whyImageSecondary}
              fallbackSrc={WHY_SECONDARY_DEFAULT}
              alt={h.imgTeam}
              h="clamp(140px, 26vw, 190px)"
              radius="var(--r-2xl)"
              className="why-img-2"
            />
            <div className="why-blob">
              <Icon name="bulb" size={22} />
              <div className="ed-badge-n tnum" style={{ marginTop: 6 }}>
                +3.4k
              </div>
              <div className="ed-badge-l">{h.activeMembers}</div>
            </div>
          </div>
          <div className="why-copy">
            <span className="eyebrow">{h.whyEyebrow}</span>
            <h2 className="ed-h2">
              {whyTitle} <span className="hl">{whyHighlight}</span>
            </h2>
            <p className="muted" style={{ fontSize: "var(--fs-lg)", lineHeight: 1.8, marginTop: 14 }}>
              {whySub}
            </p>
            <div className="check-list">
              {whys.map((w) => (
                <div key={w} className="check-row">
                  <span className="check-mark">
                    <Icon name="check" size={15} />
                  </span>
                  {w}
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>
    ) : null,

    capabilities: showCapabilities ? (
      <section key="capabilities" className="caps-band" id="feat">
        <div className="container">
          <div className="caps-head">
            <span className="eyebrow">{h.capsEyebrow}</span>
            <h2 className="ed-h2">
              {capsTitle} <span className="hl">{capsHighlight}</span>
            </h2>
          </div>
          <div className="caps-grid">
            {caps.map((cap) => (
              <div key={cap.n} className="cap-card">
                <div className="cap-top">
                  <span className="cap-num tnum">{cap.n}</span>
                  <span className="cap-ico">
                    <Icon name={cap.ico} size={22} />
                  </span>
                </div>
                <h3 className="h3" style={{ marginTop: 18 }}>
                  {cap.t}
                </h3>
                <p className="muted" style={{ marginTop: 8, lineHeight: 1.7 }}>
                  {cap.d}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>
    ) : null,

    featured: showFeatured ? (
      <section key="featured" className="container section">
        <div className="page-head" style={{ alignItems: "flex-end" }}>
          <div>
            <span className="eyebrow">{featuredSub}</span>
            <h2 className="ed-h2" style={{ marginTop: 8 }}>
              {featuredTitle}
            </h2>
          </div>
          <Link href="/explore">
            <Button variant="ghost" iconEnd="arrowR">
              {c.viewAll}
            </Button>
          </Link>
        </div>
        <div className="ws-grid">
          {featured.map((w) => (
            <WorkspaceCard key={w.id} workspace={w} dict={dict} locale={locale} />
          ))}
        </div>
      </section>
    ) : null,

    testimonials:
      showTestimonials && testimonials.length > 0 ? (
        <section key="testimonials" className="tstm-band">
          <div className="container">
            <div style={{ textAlign: "center", maxWidth: 560, margin: "0 auto 44px" }}>
              <span className="eyebrow">{h.tstmEyebrow}</span>
              <h2 className="ed-h2" style={{ marginTop: 8 }}>
                {tstmTitle} <span className="hl">{h.tstmTitle2}</span>
              </h2>
            </div>
            <div className="tstm-grid">
              {testimonials.map((m) => (
                <div key={m.key} className="tstm-card">
                  <div className="tstm-quote">
                    <Icon name="megaphone" size={20} />
                  </div>
                  <p className="tstm-text">{m.text}</p>
                  <div className="row" style={{ gap: 11, marginTop: "auto", paddingTop: 20 }}>
                    <Avatar initial={m.avatar} round />
                    <div>
                      <div style={{ fontWeight: 600, fontSize: "var(--fs-sm)" }}>{m.name}</div>
                      <div className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
                        {m.role}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>
      ) : null,
  };

  // Sanitised admin-chosen order (unknown dropped, missing appended); an unset
  // value resolves to the default order so the page is unchanged from before.
  const order = sanitizeSectionsOrder(cms.sections_order);

  return (
    <>
      {/* HERO */}
      <section className="ed-hero">
        <div className="container ed-hero-in">
          <div className="ed-hero-copy">
            <h1 className="ed-title">
              {heroTitle} <span className="hl">{heroHighlight}</span>
              {h.heroT3}
            </h1>
            <p className="ed-lead">{heroSub}</p>
            <div className="row wrap" style={{ gap: 12, marginTop: 4 }}>
              <Link href="/login">
                <Button variant="primary" size="lg" icon="user">
                  {heroCta1}
                </Button>
              </Link>
              <Link href="/login">
                <Button variant="secondary" size="lg" icon="building">
                  {heroCta2}
                </Button>
              </Link>
            </div>
            <div className="ed-stats">
              {stats.map((s) => (
                <div key={s.key} className="ed-stat">
                  <div className="ed-stat-n tnum">{s.n}</div>
                  <div className="ed-stat-l">{s.l}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="ed-collage">
            <div className="ed-img-main">
              <CoverImage src={heroImage} fallbackSrc={HERO_IMG_DEFAULT} alt={h.imgWorkspace} h="clamp(240px, 44vw, 420px)" radius="var(--r-2xl)" />
            </div>
            <div className="ed-img-sm">
              <CoverImage src={heroImageSecondary} fallbackSrc={HERO_SECONDARY_DEFAULT} alt={h.imgLounge} h="clamp(150px, 22vw, 196px)" radius="var(--r-2xl)" />
            </div>
            <div className="ed-float-card card">
              <div className="between" style={{ marginBottom: 10 }}>
                <div className="row" style={{ gap: 8 }}>
                  <span className="st-ico amber" style={{ width: 28, height: 28 }}>
                    <Icon name="grid" size={15} />
                  </span>
                  <span style={{ fontWeight: 600, fontSize: "var(--fs-sm)" }}>
                    {c.seatsFreeNow}
                  </span>
                </div>
                <span className="badge badge-success" style={{ height: 20 }}>
                  <span className="dot" />
                  {locale === "ar" ? "١٢" : "12"}
                </span>
              </div>
              <HeroSeatMini />
            </div>
            <div className="ed-float-badge">
              <div className="ed-badge-n tnum">{h.satisfaction}</div>
              <div className="ed-badge-l">{h.whyStat}</div>
            </div>
          </div>
        </div>
      </section>

      {/* MARQUEE */}
      <Marquee dict={dict} />

      {/* Reorderable sections — admin-controlled order via cms.sections_order */}
      {order.map((key) => sectionNodes[key])}

      {/* CTA band */}
      <section className="container">
        <div className="cta-band">
          <div className="cta-bulb">
            <Icon name="bulb" size={30} />
          </div>
          <h2 className="h1" style={{ maxWidth: 600, color: "#fff" }}>
            {h.ctaBandT}
          </h2>
          <p style={{ maxWidth: 480, opacity: 0.85 }}>{h.ctaBandD}</p>
          <div className="row wrap" style={{ gap: 12, justifyContent: "center", marginTop: 8 }}>
            <Link href="/login">
              <Button variant="accent" size="lg">
                {heroCta1}
              </Button>
            </Link>
            <Link href="/login">
              <Button variant="secondary" size="lg">
                {heroCta2}
              </Button>
            </Link>
          </div>
        </div>
      </section>
    </>
  );
}
