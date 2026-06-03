import { Icon } from "@/components/ui/Icon";
import { Avatar } from "@/components/ui/Avatar";
import type { Review } from "@/lib/types";
import type { PublicDict } from "./i18n";
import { interpolate } from "./i18n";

export interface ReviewListProps {
  reviews: Review[];
  avgRating: number;
  total: number;
  dict: PublicDict;
}

/** Mask a reviewer name to its first character + dots (privacy). */
function maskName(name: string): string {
  const trimmed = name.trim();
  if (!trimmed) return "•••";
  const first = Array.from(trimmed)[0];
  return `${first}•••`;
}

function Stars({ value }: { value: number }) {
  return (
    <div className="rating">
      {Array.from({ length: 5 }).map((_, j) => (
        <Icon
          key={j}
          name="star"
          size={13}
          className={j < value ? "star-fill" : ""}
          style={j < value ? undefined : { color: "var(--border-strong)" }}
        />
      ))}
    </div>
  );
}

/** Reviews block: average summary + masked individual reviews. */
export function ReviewList({ reviews, avgRating, total, dict }: ReviewListProps) {
  const d = dict.detail;

  if (reviews.length === 0) {
    return (
      <p className="muted" style={{ marginTop: 8 }}>
        {d.noReviews}
      </p>
    );
  }

  return (
    <div className="stack" style={{ gap: 14, marginTop: 24 }}>
      <div className="row" style={{ gap: 12, alignItems: "center" }}>
        <span className="ws-price tnum" style={{ fontSize: "2rem" }}>
          {avgRating.toFixed(1)}
        </span>
        <div className="stack" style={{ gap: 2 }}>
          <Stars value={Math.round(avgRating)} />
          <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
            {interpolate(d.reviewsCount, { count: total })}
          </span>
        </div>
      </div>

      {reviews.map((r) => (
        <div key={r.id} className="card card-pad review">
          <div className="row" style={{ gap: 10 }}>
            <Avatar initial={Array.from(r.reviewer_name.trim())[0] ?? "•"} round />
            <div className="grow">
              <div style={{ fontWeight: 600 }}>{maskName(r.reviewer_name)}</div>
              <Stars value={r.rating} />
            </div>
          </div>
          {r.comment && (
            <p className="muted" style={{ marginTop: 10 }}>
              {r.comment}
            </p>
          )}
        </div>
      ))}
    </div>
  );
}
