import type { ReactNode } from "react";
import { Icon } from "./Icon";

export interface RatingProps {
  value: number;
  /** Optional review count; render `reviewsLabel` alongside when provided. */
  reviews?: number | null;
  reviewsLabel?: ReactNode;
}

/** Star + numeric rating, with an optional review-count suffix. */
export function Rating({ value, reviews, reviewsLabel }: RatingProps) {
  return (
    <span className="rating">
      <Icon name="star" size={15} className="star-fill" />
      <span className="tnum" style={{ fontWeight: 600 }}>
        {value.toFixed(1)}
      </span>
      {reviews != null && (
        <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
          ({reviews}
          {reviewsLabel ? <> {reviewsLabel}</> : null})
        </span>
      )}
    </span>
  );
}
