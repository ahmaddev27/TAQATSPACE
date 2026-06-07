import { Icon } from "@/components/ui/Icon";
import type { SeatTypePrice } from "@/lib/types";
import type { PublicDict } from "./i18n";
import { seatTypeLabel, toNumber } from "./helpers";

export interface SeatPricingProps {
  /** Per-seat-type pricing rows from the workspace (may be undefined on legacy data). */
  seatTypes?: SeatTypePrice[];
  /** Fallback monthly price when the workspace has no seat-type rows. */
  monthlyPrice: number;
  dict: PublicDict;
}

interface PriceCard {
  type: string;
  monthly: number | null;
  daily: number | null;
}

/**
 * Render seat-type pricing from the workspace's `seat_types` (enabled rows only).
 * Falls back to a single card driven by `price_per_month` when no rows exist so
 * legacy workspaces still display a price.
 */
export function SeatPricing({ seatTypes, monthlyPrice, dict }: SeatPricingProps) {
  const d = dict.detail;
  const c = dict.common;

  const enabled = (seatTypes ?? []).filter((row) => row.enabled);

  const cards: PriceCard[] =
    enabled.length > 0
      ? enabled.map((row) => ({
          type: row.type,
          monthly: row.price_monthly != null ? toNumber(row.price_monthly) : null,
          daily: row.price_daily != null ? toNumber(row.price_daily) : null,
        }))
      : [{ type: "flexible", monthly: monthlyPrice, daily: null }];

  // The "fixed" desk is the conventional headline plan when present.
  const popularType = cards.some((card) => card.type === "fixed")
    ? "fixed"
    : cards[0]?.type;

  const monthlyUnit = c.perMonth.replace(/^\/\s*/, "");
  const dailyUnit = c.perDay.replace(/^\/\s*/, "");

  return (
    <div className="stack" style={{ gap: 16, marginTop: 24 }}>
      <h3 className="h3">{d.seatTypesTitle}</h3>
      <div className="plan-grid">
        {cards.map((card) => {
          const popular = card.type === popularType;
          return (
            <div
              key={card.type}
              className={`card plan-card ${popular ? "is-popular" : ""}`}
            >
              {popular && <span className="plan-badge">★</span>}
              <div className="plan-name">{seatTypeLabel(card.type, d)}</div>

              {card.monthly != null && (
                <div className="row" style={{ gap: 6, alignItems: "baseline" }}>
                  <span className="ws-price tnum" style={{ fontSize: "1.9rem" }}>
                    {c.currency}
                    {card.monthly}
                  </span>
                  <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                    / {monthlyUnit}
                  </span>
                </div>
              )}

              {card.daily != null && (
                <div
                  className="row"
                  style={{ gap: 6, alignItems: "baseline", marginTop: 6 }}
                >
                  <span className="tnum" style={{ fontSize: "1.1rem", fontWeight: 600 }}>
                    {c.currency}
                    {card.daily}
                  </span>
                  <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                    / {dailyUnit}
                  </span>
                </div>
              )}

              <div className="divider" style={{ margin: "14px 0" }} />
              <div className="stack" style={{ gap: 10 }}>
                {[dict.marquee.internet, dict.marquee.support].map((f) => (
                  <div
                    key={f}
                    className="row"
                    style={{ gap: 9, fontSize: "var(--fs-sm)" }}
                  >
                    <Icon name="check" size={16} style={{ color: "var(--success)" }} />
                    {f}
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
