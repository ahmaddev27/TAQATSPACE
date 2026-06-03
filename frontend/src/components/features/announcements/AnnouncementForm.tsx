"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Drawer } from "@/components/ui/Drawer";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Segmented } from "@/components/ui/Segmented";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import {
  createAnnouncement,
  updateAnnouncement,
} from "@/lib/actions/announcements";
import type {
  Announcement,
  AnnouncementInput,
  AnnouncementType,
} from "@/lib/api/announcements";
import {
  TYPE_EMOJI,
  fromDatetimeLocal,
  toDatetimeLocal,
} from "./format";

export interface AnnouncementFormProps {
  /** When provided, the form edits this announcement; otherwise it creates one. */
  announcement: Announcement | null;
  onClose: () => void;
}

const TYPES: AnnouncementType[] = ["offer", "info", "alert"];

type ScheduleMode = "now" | "schedule";

interface FormState {
  type: AnnouncementType;
  title: string;
  body: string;
  scheduleMode: ScheduleMode;
  /** `datetime-local` value, only used while scheduleMode === "schedule". */
  publishAt: string;
  hasExpiry: boolean;
  expiresAt: string;
}

function initialState(a: Announcement | null): FormState {
  // A future publish time (or none) means the author intends a schedule/draft.
  const scheduled = a?.published_at != null && a.status === "draft";
  return {
    type: a?.type ?? "offer",
    title: a?.title ?? "",
    body: a?.body ?? "",
    scheduleMode: scheduled ? "schedule" : "now",
    publishAt: scheduled ? toDatetimeLocal(a?.published_at) : "",
    hasExpiry: a?.expires_at != null,
    expiresAt: toDatetimeLocal(a?.expires_at),
  };
}

/** Slide-in create/edit panel for a workspace announcement. */
export function AnnouncementForm({
  announcement,
  onClose,
}: AnnouncementFormProps) {
  const t = useTranslations("announcements");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [form, setForm] = useState<FormState>(() =>
    initialState(announcement),
  );
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const isEdit = announcement !== null;

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  const buildPayload = (): AnnouncementInput => ({
    type: form.type,
    title: form.title.trim(),
    body: form.body.trim(),
    published_at:
      form.scheduleMode === "now"
        ? new Date().toISOString()
        : fromDatetimeLocal(form.publishAt),
    expires_at: form.hasExpiry ? fromDatetimeLocal(form.expiresAt) : null,
  });

  const submit = () => {
    const payload = buildPayload();
    startTransition(async () => {
      const res = isEdit
        ? await updateAnnouncement(announcement.id, payload)
        : await createAnnouncement(payload);

      if (res.ok) {
        toast({ tone: "ok", title: isEdit ? t("updated") : t("created") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("saveFailed"), body: res.message });
      }
    });
  };

  return (
    <Drawer
      title={isEdit ? t("editTitle") : t("newTitle")}
      onClose={onClose}
      closeLabel={t("close")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t("cancel")}
          </Button>
          <Button
            variant="primary"
            icon="check"
            loading={pending}
            onClick={submit}
          >
            {t("save")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 18 }}>
        <Field label={t("fType")} error={errors.type?.[0]}>
          <div className="row" style={{ gap: 10, flexWrap: "wrap" }}>
            {TYPES.map((type) => {
              const active = form.type === type;
              return (
                <button
                  key={type}
                  type="button"
                  onClick={() => set("type", type)}
                  aria-pressed={active}
                  className="card"
                  style={{
                    flex: "1 1 120px",
                    padding: "12px 10px",
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "center",
                    gap: 6,
                    cursor: "pointer",
                    borderColor: active
                      ? "var(--primary)"
                      : "var(--border)",
                    boxShadow: active ? "var(--sh-sm)" : "none",
                    background: active
                      ? "var(--primary-soft)"
                      : "var(--surface)",
                  }}
                >
                  <span style={{ fontSize: 22 }} aria-hidden="true">
                    {TYPE_EMOJI[type]}
                  </span>
                  <span
                    style={{
                      fontSize: "var(--fs-sm)",
                      fontWeight: 600,
                      color: active ? "var(--primary)" : "var(--text)",
                    }}
                  >
                    {t(`types.${type}`)}
                  </span>
                </button>
              );
            })}
          </div>
        </Field>

        <Field label={t("fTitle")} error={errors.title?.[0]}>
          <Input
            value={form.title}
            maxLength={150}
            onChange={(e) => set("title", e.target.value)}
            placeholder={t("fTitlePlaceholder")}
          />
        </Field>

        <Field label={t("fBody")} error={errors.body?.[0]}>
          <Textarea
            rows={5}
            value={form.body}
            maxLength={5000}
            onChange={(e) => set("body", e.target.value)}
            placeholder={t("fBodyPlaceholder")}
          />
        </Field>

        <Field label={t("fSchedule")} error={errors.published_at?.[0]}>
          <Segmented
            value={form.scheduleMode}
            onChange={(id) => set("scheduleMode", id as ScheduleMode)}
            items={[
              { id: "now", label: t("publishNow") },
              { id: "schedule", label: t("schedule") },
            ]}
          />
        </Field>

        {form.scheduleMode === "schedule" && (
          <Field
            label={t("fPublishAt")}
            hint={t("fPublishAtHint")}
            error={errors.published_at?.[0]}
          >
            <Input
              type="datetime-local"
              value={form.publishAt}
              onChange={(e) => set("publishAt", e.target.value)}
            />
          </Field>
        )}

        <Checkbox
          checked={form.hasExpiry}
          onChange={(e) => set("hasExpiry", e.target.checked)}
        >
          {t("fHasExpiry")}
        </Checkbox>

        {form.hasExpiry && (
          <Field
            label={t("fExpiresAt")}
            hint={t("fExpiresAtHint")}
            error={errors.expires_at?.[0]}
          >
            <Input
              type="datetime-local"
              value={form.expiresAt}
              onChange={(e) => set("expiresAt", e.target.value)}
            />
          </Field>
        )}
      </div>
    </Drawer>
  );
}
