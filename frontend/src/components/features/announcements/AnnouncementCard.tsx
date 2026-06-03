"use client";

import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import type {
  Announcement,
  AnnouncementStatus,
} from "@/lib/api/announcements";
import { TypeBadge } from "./TypeBadge";
import { shortDateTime } from "./format";

export interface AnnouncementCardProps {
  announcement: Announcement;
  pending?: boolean;
  onEdit: () => void;
  onDelete: () => void;
  onPublish: () => void;
}

/** Foundation `.badge-*` variant for each lifecycle status pill. */
const STATUS_VARIANT: Record<AnnouncementStatus, string> = {
  live: "badge-success",
  draft: "badge-neutral",
  expired: "badge-danger",
};

/** A single announcement: type badge, title/body, schedule meta, status + actions. */
export function AnnouncementCard({
  announcement,
  pending,
  onEdit,
  onDelete,
  onPublish,
}: AnnouncementCardProps) {
  const t = useTranslations("announcements");
  const { type, title, body, status, published_at, expires_at } = announcement;

  return (
    <div className="card card-pad stack" style={{ gap: 12 }}>
      <div className="between" style={{ alignItems: "flex-start" }}>
        <div className="row" style={{ gap: 8, flexWrap: "wrap" }}>
          <TypeBadge type={type} />
          <span className={`badge ${STATUS_VARIANT[status]}`}>
            <span className="dot" aria-hidden="true" />
            {t(`status.${status}`)}
          </span>
        </div>
        <div className="row" style={{ gap: 4 }}>
          {status === "draft" && (
            <Button
              variant="secondary"
              size="sm"
              icon="send"
              loading={pending}
              onClick={onPublish}
            >
              {t("publish")}
            </Button>
          )}
          <Button
            variant="ghost"
            size="sm"
            icon="edit"
            onClick={onEdit}
            aria-label={t("edit")}
          />
          <Button
            variant="ghost"
            size="sm"
            icon="trash"
            onClick={onDelete}
            aria-label={t("delete")}
          />
        </div>
      </div>

      <div className="stack" style={{ gap: 4 }}>
        <h3 className="h3" style={{ fontSize: "var(--fs-lg)" }}>
          {title}
        </h3>
        <p
          className="muted"
          style={{
            fontSize: "var(--fs-sm)",
            whiteSpace: "pre-wrap",
            wordBreak: "break-word",
          }}
        >
          {body}
        </p>
      </div>

      <div
        className="row"
        style={{ gap: 18, flexWrap: "wrap", marginTop: 2 }}
      >
        <Meta
          icon="calendar"
          label={t("publishedAt")}
          value={
            published_at ? shortDateTime(published_at) : t("notPublished")
          }
        />
        <Meta
          icon="clock"
          label={t("expiresAt")}
          value={expires_at ? shortDateTime(expires_at) : t("noExpiry")}
        />
      </div>
    </div>
  );
}

function Meta({
  icon,
  label,
  value,
}: {
  icon: "calendar" | "clock";
  label: string;
  value: string;
}) {
  return (
    <div className="row" style={{ gap: 6 }}>
      <span className="muted-3" style={{ display: "inline-flex" }}>
        <Icon name={icon} size={15} />
      </span>
      <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
        {label}:
      </span>
      <span
        className="tnum"
        style={{ fontSize: "var(--fs-xs)", fontWeight: 500, direction: "ltr" }}
      >
        {value}
      </span>
    </div>
  );
}
