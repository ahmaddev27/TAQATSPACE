import type { PublicDict } from "./i18n";

export interface MarqueeProps {
  dict: PublicDict;
}

/** CSS-animated value-prop ticker (ported from prototype `Marquee`). */
export function Marquee({ dict }: MarqueeProps) {
  const m = dict.marquee;
  const items = [
    m.verified,
    m.instant,
    m.invoices,
    m.seatmaps,
    m.internet,
    m.support,
    m.cities,
    m.noFees,
  ];
  const doubled = [...items, ...items];

  return (
    <div className="marquee" aria-hidden="true">
      <div className="marquee-track">
        {doubled.map((item, i) => (
          <span key={i} className="marquee-item">
            {item}
            <span className="marquee-star">✦</span>
          </span>
        ))}
      </div>
    </div>
  );
}
