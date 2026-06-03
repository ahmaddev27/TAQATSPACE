"use client";

import { useTranslations } from "next-intl";
import type { AnnouncementType } from "@/lib/api/announcements";
import { TYPE_BADGE_VARIANT, TYPE_EMOJI } from "./format";

export interface TypeBadgeProps {
  type: AnnouncementType;
}

/** Colour-coded pill for an announcement type, fronted by its emoji glyph. */
export function TypeBadge({ type }: TypeBadgeProps) {
  const t = useTranslations("announcements");
  return (
    <span className={`badge ${TYPE_BADGE_VARIANT[type]}`}>
      <span aria-hidden="true">{TYPE_EMOJI[type]}</span>
      {t(`types.${type}`)}
    </span>
  );
}
