"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { submitReview } from "@/lib/actions/reviews";
import type { Review } from "@/lib/types";
import { formatDate } from "./format";

export interface ReviewFormProps {
  workspaceId: string;
  workspaceName: string;
  /** The freelancer's existing review, when they've already rated this space. */
  existingReview?: Review | null;
}

/** Static 1–5 star row reflecting a saved rating. */
function StarRow({ rating }: { rating: number }) {
  return (
    <div className="star-input" aria-hidden="true">
      {[1, 2, 3, 4, 5].map((n) => (
        <Icon
          key={n}
          name="star"
          size={24}
          className={rating >= n ? "star-fill" : "star-empty"}
        />
      ))}
    </div>
  );
}

/**
 * Freelancer rates the workspace they're subscribed to. A space can be reviewed
 * once: when a review already exists (or has just been posted) the saved rating,
 * comment, and date are shown read-only instead of the form.
 */
export function ReviewForm({
  workspaceId,
  workspaceName,
  existingReview = null,
}: ReviewFormProps) {
  const t = useTranslations("freelancer.review");
  const { toast } = useToast();
  const [rating, setRating] = useState(0);
  const [hover, setHover] = useState(0);
  const [comment, setComment] = useState("");
  const [posted, setPosted] = useState<Review | null>(null);
  const [pending, startTransition] = useTransition();

  // The review to display read-only: a freshly-posted one wins, else the one
  // loaded from the server.
  const review = posted ?? existingReview;

  const submit = () => {
    if (rating < 1) {
      toast({ tone: "err", title: t("pickRating") });
      return;
    }
    startTransition(async () => {
      const res = await submitReview({
        workspace_id: workspaceId,
        rating,
        comment,
      });
      if (res.ok) {
        setPosted({
          id: "local",
          reviewer_name: "",
          rating,
          comment: comment.trim() || null,
          created_at: new Date().toISOString(),
        });
        toast({ tone: "ok", title: t("thanks") });
      } else if (res.status === 409) {
        // Already reviewed — surface a neutral notice (the page reload will then
        // render the saved review).
        toast({ tone: "info", title: t("already") });
      } else {
        toast({ tone: "err", title: t("failed"), body: res.message });
      }
    });
  };

  if (review) {
    return (
      <div className="card card-pad stack" style={{ gap: 12 }}>
        <div className="between">
          <h3 className="h3">{t("yourReview")}</h3>
          <span className="muted-3 tnum ltr" style={{ fontSize: "var(--fs-sm)" }}>
            {formatDate(review.created_at)}
          </span>
        </div>
        <StarRow rating={review.rating} />
        {review.comment && (
          <p className="muted" style={{ lineHeight: 1.8 }}>
            {review.comment}
          </p>
        )}
      </div>
    );
  }

  const shown = hover || rating;

  return (
    <div className="card card-pad stack" style={{ gap: 14 }}>
      <div>
        <h3 className="h3">{t("title")}</h3>
        <p className="muted" style={{ marginTop: 4 }}>
          {t("subtitle", { name: workspaceName })}
        </p>
      </div>

      <div className="star-input" onMouseLeave={() => setHover(0)}>
        {[1, 2, 3, 4, 5].map((n) => (
          <button
            key={n}
            type="button"
            className="star-btn"
            aria-label={t("starLabel", { n })}
            onMouseEnter={() => setHover(n)}
            onFocus={() => setHover(n)}
            onClick={() => setRating(n)}
          >
            <Icon
              name="star"
              size={30}
              className={shown >= n ? "star-fill" : "star-empty"}
            />
          </button>
        ))}
      </div>

      <Textarea
        rows={3}
        placeholder={t("commentPlaceholder")}
        value={comment}
        onChange={(e) => setComment(e.target.value)}
      />

      <div className="between">
        <span />
        <Button
          variant="primary"
          icon="send"
          loading={pending}
          onClick={submit}
        >
          {t("submit")}
        </Button>
      </div>
    </div>
  );
}
