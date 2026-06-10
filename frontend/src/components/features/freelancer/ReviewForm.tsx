"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { submitReview } from "@/lib/actions/reviews";

export interface ReviewFormProps {
  workspaceId: string;
  workspaceName: string;
}

/** Freelancer rates the workspace they're subscribed to (1–5 stars + comment). */
export function ReviewForm({ workspaceId, workspaceName }: ReviewFormProps) {
  const t = useTranslations("freelancer.review");
  const { toast } = useToast();
  const [rating, setRating] = useState(0);
  const [hover, setHover] = useState(0);
  const [comment, setComment] = useState("");
  const [done, setDone] = useState(false);
  const [pending, startTransition] = useTransition();

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
        setDone(true);
        toast({ tone: "ok", title: t("thanks") });
      } else if (res.status === 409) {
        // Already reviewed — treat as done rather than an error.
        setDone(true);
        toast({ tone: "info", title: t("already") });
      } else {
        toast({ tone: "err", title: t("failed"), body: res.message });
      }
    });
  };

  if (done) {
    return (
      <div className="card card-pad" style={{ textAlign: "center" }}>
        <span className="st-ico" style={{ margin: "0 auto" }}>
          <Icon name="checkCircle" size={22} />
        </span>
        <p className="muted" style={{ marginTop: 10 }}>
          {t("thanks")}
        </p>
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
