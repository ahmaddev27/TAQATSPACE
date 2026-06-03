import { Icon } from "@/components/ui/Icon";
import type { PublicDict } from "./i18n";

export interface SeatPricingProps {
  monthlyPrice: number;
  dict: PublicDict;
}

/** Derive seat-type pricing cards from the workspace monthly price. */
export function SeatPricing({ monthlyPrice, dict }: SeatPricingProps) {
  const d = dict.detail;
  const c = dict.common;
  // Daily ≈ monthly / 22 working days; private ≈ 1.6× monthly (typical premium).
  const daily = Math.max(1, Math.round(monthlyPrice / 22));
  const privatePrice = Math.round(monthlyPrice * 1.6);

  const plans = [
    {
      name: d.seatOpen,
      price: daily,
      unit: c.perDay.replace(/^\/\s*/, ""),
      popular: false,
      feats: [dict.marquee.internet, dict.marquee.support],
    },
    {
      name: d.seatFixed,
      price: monthlyPrice,
      unit: c.perMonth.replace(/^\/\s*/, ""),
      popular: true,
      feats: [dict.marquee.internet, dict.marquee.seatmaps, dict.marquee.support],
    },
    {
      name: d.seatPrivate,
      price: privatePrice,
      unit: c.perMonth.replace(/^\/\s*/, ""),
      popular: false,
      feats: [dict.marquee.internet, dict.marquee.invoices, dict.marquee.support],
    },
  ];

  return (
    <div className="stack" style={{ gap: 16, marginTop: 24 }}>
      <h3 className="h3">{d.seatTypesTitle}</h3>
      <div className="plan-grid">
        {plans.map((p) => (
          <div key={p.name} className={`card plan-card ${p.popular ? "is-popular" : ""}`}>
            {p.popular && <span className="plan-badge">★</span>}
            <div className="plan-name">{p.name}</div>
            <div className="row" style={{ gap: 6, alignItems: "baseline" }}>
              <span className="ws-price tnum" style={{ fontSize: "1.9rem" }}>
                {c.currency}
                {p.price}
              </span>
              <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                / {p.unit}
              </span>
            </div>
            <div className="divider" style={{ margin: "14px 0" }} />
            <div className="stack" style={{ gap: 10 }}>
              {p.feats.map((f) => (
                <div key={f} className="row" style={{ gap: 9, fontSize: "var(--fs-sm)" }}>
                  <Icon name="check" size={16} style={{ color: "var(--success)" }} />
                  {f}
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
