import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";

export interface PlanModel {
  id: string;
  name: string;
  nameEn: string;
  price: number;
  unit: string;
  unitEn: string;
  popular?: boolean;
  feats: string[];
  featsEn: string[];
}

/**
 * Static plan catalogue mirroring the prototype (`data.jsx` PLANS). Shown on the
 * subscription page as the "available plans" grid. Pricing is illustrative until
 * a plans endpoint exists; choosing a plan routes the member to explore.
 */
export const PLANS: PlanModel[] = [
  {
    id: "daily",
    name: "يومي",
    nameEn: "Daily",
    price: 8,
    unit: "يوم",
    unitEn: "day",
    feats: ["مقعد مفتوح", "إنترنت 50 ميجا", "قهوة مجانية"],
    featsEn: ["Open seat", "50 Mbps internet", "Free coffee"],
  },
  {
    id: "monthly",
    name: "شهري",
    nameEn: "Monthly",
    price: 35,
    unit: "شهر",
    unitEn: "month",
    popular: true,
    feats: ["مقعد ثابت", "إنترنت 100 ميجا", "قهوة + شاي", "خصم 10% على القاعات"],
    featsEn: [
      "Fixed desk",
      "100 Mbps internet",
      "Coffee + tea",
      "10% off meeting rooms",
    ],
  },
  {
    id: "quarterly",
    name: "ربع سنوي",
    nameEn: "Quarterly",
    price: 95,
    unit: "٣ أشهر",
    unitEn: "3 months",
    feats: ["مكتب مخصّص", "إنترنت 200 ميجا", "كل المرافق", "٤ ساعات قاعات شهرياً"],
    featsEn: [
      "Dedicated desk",
      "200 Mbps internet",
      "All amenities",
      "4h meeting rooms / mo",
    ],
  },
];

export interface PlanCardProps {
  plan: PlanModel;
  locale: string;
  currency: string;
  popularLabel: string;
  chooseLabel: string;
  perUnitLabel: (unit: string) => string;
  onChoose?: () => void;
}

/** A single pricing card. Ports the prototype `PlanCard` className set exactly. */
export function PlanCard({
  plan,
  locale,
  currency,
  popularLabel,
  chooseLabel,
  perUnitLabel,
  onChoose,
}: PlanCardProps) {
  const ar = locale === "ar";
  const feats = ar ? plan.feats : plan.featsEn;
  const unit = ar ? plan.unit : plan.unitEn;

  return (
    <div className={`card plan-card ${plan.popular ? "is-popular" : ""}`.trim()}>
      {plan.popular && <span className="plan-badge">{popularLabel}</span>}
      <div className="plan-name">{ar ? plan.name : plan.nameEn}</div>
      <div className="row" style={{ gap: 6, alignItems: "baseline" }}>
        <span className="ws-price tnum" style={{ fontSize: "1.9rem" }}>
          {currency}
          {plan.price}
        </span>
        <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
          {perUnitLabel(unit)}
        </span>
      </div>
      <div className="divider" style={{ margin: "14px 0" }} />
      <div className="stack" style={{ gap: 10 }}>
        {feats.map((f) => (
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
      <div style={{ height: 16 }} />
      <Button
        variant={plan.popular ? "primary" : "secondary"}
        block
        onClick={onChoose}
      >
        {chooseLabel}
      </Button>
    </div>
  );
}
