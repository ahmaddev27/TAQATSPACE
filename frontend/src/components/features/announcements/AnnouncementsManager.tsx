"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Segmented } from "@/components/ui/Segmented";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { useToast } from "@/components/providers/ToastProvider";
import {
  deleteAnnouncement,
  publishAnnouncement,
} from "@/lib/actions/announcements";
import type {
  Announcement,
  AnnouncementStatus,
} from "@/lib/api/announcements";
import { AnnouncementCard } from "./AnnouncementCard";
import { AnnouncementForm } from "./AnnouncementForm";

export interface AnnouncementsManagerProps {
  announcements: Announcement[];
}

type Editing = { mode: "create" } | { mode: "edit"; item: Announcement } | null;
type StatusFilter = "all" | AnnouncementStatus;

const FILTERS: StatusFilter[] = ["all", "live", "draft", "expired"];

/** List of announcement cards with status filtering + a slide-in create/edit form. */
export function AnnouncementsManager({
  announcements,
}: AnnouncementsManagerProps) {
  const t = useTranslations("announcements");
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();

  const [editing, setEditing] = useState<Editing>(null);
  const [filter, setFilter] = useState<StatusFilter>("all");
  /** Id of the row whose inline action is mid-flight, for a scoped spinner. */
  const [busyId, setBusyId] = useState<string | null>(null);

  const visible = useMemo(
    () =>
      filter === "all"
        ? announcements
        : announcements.filter((a) => a.status === filter),
    [announcements, filter],
  );

  const handleDelete = async (item: Announcement) => {
    const ok = await confirm({
      title: t("delete"),
      message: t("deleteConfirm"),
      confirmLabel: t("delete"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;
    setBusyId(item.id);
    startTransition(async () => {
      const res = await deleteAnnouncement(item.id);
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t("deleted") });
      } else {
        toast({ tone: "err", title: t("deleteFailed"), body: res.message });
      }
    });
  };

  const handlePublish = (item: Announcement) => {
    setBusyId(item.id);
    startTransition(async () => {
      const res = await publishAnnouncement(item.id);
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t("published") });
      } else {
        toast({ tone: "err", title: t("publishFailed"), body: res.message });
      }
    });
  };

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="between" style={{ flexWrap: "wrap", gap: 12 }}>
        <Segmented
          value={filter}
          onChange={(id) => setFilter(id as StatusFilter)}
          items={FILTERS.map((f) => ({
            id: f,
            label: f === "all" ? t("filterAll") : t(`status.${f}`),
          }))}
        />
        <Button
          variant="primary"
          icon="plus"
          onClick={() => setEditing({ mode: "create" })}
        >
          {t("create")}
        </Button>
      </div>

      {visible.length === 0 ? (
        <div className="card empty-state" style={{ minHeight: 260 }}>
          <span className="st-ico" style={{ width: 48, height: 48 }}>
            <Icon name="megaphone" />
          </span>
          <div>
            <div className="h3">
              {announcements.length === 0 ? t("emptyTitle") : t("emptyFiltered")}
            </div>
            <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
              {announcements.length === 0
                ? t("emptyBody")
                : t("emptyFilteredBody")}
            </div>
          </div>
          {announcements.length === 0 && (
            <Button
              variant="primary"
              icon="plus"
              onClick={() => setEditing({ mode: "create" })}
            >
              {t("create")}
            </Button>
          )}
        </div>
      ) : (
        <div className="stack" style={{ gap: 14 }}>
          {visible.map((item) => (
            <AnnouncementCard
              key={item.id}
              announcement={item}
              pending={pending && busyId === item.id}
              onEdit={() => setEditing({ mode: "edit", item })}
              onDelete={() => handleDelete(item)}
              onPublish={() => handlePublish(item)}
            />
          ))}
        </div>
      )}

      {editing && (
        <AnnouncementForm
          announcement={editing.mode === "edit" ? editing.item : null}
          onClose={() => setEditing(null)}
        />
      )}
    </div>
  );
}
