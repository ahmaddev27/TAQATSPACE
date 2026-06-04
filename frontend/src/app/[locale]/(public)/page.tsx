import { Link } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { CoverImage } from "@/components/ui/CoverImage";
import { listWorkspaces } from "@/lib/api/workspaces";
import type { Workspace } from "@/lib/types";
import { getPublicDict } from "@/components/features/public/i18n";
import { WorkspaceCard } from "@/components/features/public/WorkspaceCard";
import { HeroSeatMini } from "@/components/features/public/HeroSeatMini";
import { Marquee } from "@/components/features/public/Marquee";

export const dynamic = "force-dynamic";

export default async function HomePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const dict = getPublicDict(locale);
  const h = dict.home;
  const c = dict.common;

  let featured: Workspace[] = [];
  try {
    const page = await listWorkspaces({ sort: "rating", page: 1 });
    featured = page.data.slice(0, 6);
  } catch {
    featured = [];
  }

  const stats = [
    { n: "+128", l: h.statSpaces },
    { n: "3,400", l: h.statFree },
    { n: "72%", l: h.statOcc },
    { n: "24/7", l: h.statAlways },
  ];
  const whys = [h.why1, h.why2, h.why3, h.why4];
  const caps = [
    { n: "01", ico: "search", t: dict.caps["1"], d: dict.caps["1d"] },
    { n: "02", ico: "calendar", t: dict.caps["2"], d: dict.caps["2d"] },
    { n: "03", ico: "receipt", t: dict.caps["3"], d: dict.caps["3d"] },
    { n: "04", ico: "grid", t: dict.caps["4"], d: dict.caps["4d"] },
    { n: "05", ico: "wifi", t: dict.caps["5"], d: dict.caps["5d"] },
    { n: "06", ico: "chart", t: dict.caps["6"], d: dict.caps["6d"] },
  ] as const;
  const testimonials = [
    { text: dict.tstm["1"], name: dict.tstm["1n"], role: dict.tstm["1r"], avatar: dict.tstm["1a"] },
    { text: dict.tstm["2"], name: dict.tstm["2n"], role: dict.tstm["2r"], avatar: dict.tstm["2a"] },
    { text: dict.tstm["3"], name: dict.tstm["3n"], role: dict.tstm["3r"], avatar: dict.tstm["3a"] },
  ];

  return (
    <>
      {/* HERO */}
      <section className="ed-hero">
        <div className="container ed-hero-in">
          <div className="ed-hero-copy">
            <h1 className="ed-title">
              {h.heroT1} <span className="hl">{h.heroT2}</span>
              {h.heroT3}
            </h1>
            <p className="ed-lead">{h.heroSub}</p>
            <div className="row wrap" style={{ gap: 12, marginTop: 4 }}>
              <Link href="/register/freelancer">
                <Button variant="primary" size="lg" icon="user">
                  {h.heroCta1}
                </Button>
              </Link>
              <Link href="/register/workspace">
                <Button variant="secondary" size="lg" icon="building">
                  {h.heroCta2}
                </Button>
              </Link>
            </div>
            <div className="ed-stats">
              {stats.map((s) => (
                <div key={s.l} className="ed-stat">
                  <div className="ed-stat-n tnum">{s.n}</div>
                  <div className="ed-stat-l">{s.l}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="ed-collage">
            <div className="ed-img-main">
              <CoverImage src="/images/workspaces/placeholder-1.svg" alt={h.imgWorkspace} h="clamp(240px, 44vw, 420px)" radius="var(--r-2xl)" />
            </div>
            <div className="ed-img-sm">
              <CoverImage src="/images/workspaces/placeholder-3.svg" alt={h.imgLounge} h="clamp(150px, 22vw, 196px)" radius="var(--r-2xl)" />
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

      {/* WHY / ABOUT */}
      <section className="container section" id="about">
        <div className="why-grid">
          <div className="why-collage">
            <CoverImage
              src="/images/workspaces/placeholder-2.svg"
              alt={h.imgInside}
              h="clamp(190px, 36vw, 300px)"
              radius="var(--r-2xl)"
              className="why-img-1"
            />
            <CoverImage
              src="/images/workspaces/placeholder-1.svg"
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
              {h.whyTitle1} <span className="hl">{h.whyTitle2}</span>
            </h2>
            <p className="muted" style={{ fontSize: "var(--fs-lg)", lineHeight: 1.8, marginTop: 14 }}>
              {h.whySub}
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

      {/* CAPABILITIES */}
      <section className="caps-band" id="feat">
        <div className="container">
          <div className="caps-head">
            <span className="eyebrow">{h.capsEyebrow}</span>
            <h2 className="ed-h2">
              {h.capsTitle1} <span className="hl">{h.capsTitle2}</span>
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

      {/* FEATURED SPACES */}
      {featured.length > 0 && (
        <section className="container section">
          <div className="page-head" style={{ alignItems: "flex-end" }}>
            <div>
              <span className="eyebrow">{h.featuredEyebrow}</span>
              <h2 className="ed-h2" style={{ marginTop: 8 }}>
                {h.featured}
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
      )}

      {/* TESTIMONIALS */}
      <section className="tstm-band">
        <div className="container">
          <div style={{ textAlign: "center", maxWidth: 560, margin: "0 auto 44px" }}>
            <span className="eyebrow">{h.tstmEyebrow}</span>
            <h2 className="ed-h2" style={{ marginTop: 8 }}>
              {h.tstmTitle1} <span className="hl">{h.tstmTitle2}</span>
            </h2>
          </div>
          <div className="tstm-grid">
            {testimonials.map((m) => (
              <div key={m.name} className="tstm-card">
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
            <Link href="/register/freelancer">
              <Button variant="accent" size="lg">
                {h.heroCta1}
              </Button>
            </Link>
            <Link href="/register/workspace">
              <Button variant="secondary" size="lg">
                {h.heroCta2}
              </Button>
            </Link>
          </div>
        </div>
      </section>
    </>
  );
}
