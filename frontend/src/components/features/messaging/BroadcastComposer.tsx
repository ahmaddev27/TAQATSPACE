"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Textarea } from "@/components/ui/Textarea";
import { Checkbox } from "@/components/ui/Checkbox";
import { Segmented } from "@/components/ui/Segmented";
import { useToast } from "@/components/providers/ToastProvider";
import type { ActionResult } from "@/lib/actions/client";
import type {
  BroadcastAudience,
  BroadcastChannel,
  BroadcastInput,
  BroadcastResult,
} from "@/lib/types";

/** A selectable filter option for a segment dropdown (role / status). */
export interface SegmentOption {
  value: string;
  label: string;
}

/** A pickable recipient for the "specific" audience multi-select. */
export interface RecipientOption {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
}

export interface BroadcastComposerProps {
  /**
   * Segment dropdowns to render when audience = "segment". The admin scope
   * passes role + status; the owner scope passes a single subscription-status
   * dropdown. An empty list hides the segment option entirely.
   */
  segmentFields: Array<{
    /** Maps to `segment.role` or `segment.status` server-side. */
    key: "role" | "status";
    label: string;
    /** A leading "any" option is rendered automatically. */
    options: SegmentOption[];
  }>;
  /** Full recipient roster for the "specific" picker (already loaded server-side). */
  recipients: RecipientOption[];
  /** The scope's broadcast Server Action. */
  action: (input: BroadcastInput) => Promise<ActionResult<BroadcastResult>>;
}

const MAX_VISIBLE_RECIPIENTS = 50;

/**
 * Compose-and-broadcast form shared by the admin (platform) and owner
 * (workspace) surfaces. It owns channel/audience/segment/recipient state and
 * submits through the scope-specific action, surfacing the queued/skipped
 * summary as a toast. The audience vocabularies are injected so this component
 * stays scope-agnostic.
 */
export function BroadcastComposer({
  segmentFields,
  recipients,
  action,
}: BroadcastComposerProps) {
  const t = useTranslations("broadcast");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [channels, setChannels] = useState<BroadcastChannel[]>(["email"]);
  const [audience, setAudience] = useState<BroadcastAudience>("all");
  const [segment, setSegment] = useState<Record<string, string>>({});
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [search, setSearch] = useState("");
  const [subject, setSubject] = useState("");
  const [body, setBody] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const wantsEmail = channels.includes("email");
  const wantsSms = channels.includes("sms");

  const toggleChannel = (channel: BroadcastChannel) => {
    setChannels((prev) =>
      prev.includes(channel)
        ? prev.filter((c) => c !== channel)
        : [...prev, channel],
    );
  };

  const filteredRecipients = useMemo(() => {
    const term = search.trim().toLowerCase();
    const matches = term
      ? recipients.filter(
          (r) =>
            r.name.toLowerCase().includes(term) ||
            (r.email ?? "").toLowerCase().includes(term),
        )
      : recipients;
    return matches.slice(0, MAX_VISIBLE_RECIPIENTS);
  }, [recipients, search]);

  const toggleRecipient = (id: string) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
    );
  };

  /** Cheap recipient-count hint for the "all"/"segment" audiences. */
  const recipientHint = useMemo(() => {
    if (audience === "specific") return selectedIds.length;
    if (audience === "all") return recipients.length;
    // Segment: we can only cheaply estimate "specific"/"all"; for a segment the
    // server is the source of truth, so we hint with the full roster size as an
    // upper bound rather than a precise count.
    return recipients.length;
  }, [audience, selectedIds.length, recipients.length]);

  const validateLocal = (): boolean => {
    const next: Record<string, string[]> = {};
    if (channels.length === 0) next.channels = [t("errors.channelRequired")];
    if (wantsEmail && subject.trim() === "")
      next.subject = [t("errors.subjectRequired")];
    if (body.trim() === "") next.body = [t("errors.bodyRequired")];
    if (audience === "specific" && selectedIds.length === 0)
      next.user_ids = [t("errors.recipientsRequired")];
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const submit = () => {
    if (!validateLocal()) return;

    const input: BroadcastInput = {
      channels,
      audience,
      body: body.trim(),
      ...(wantsEmail ? { subject: subject.trim() } : {}),
      ...(audience === "segment"
        ? {
            segment: {
              role: segment.role || null,
              status: segment.status || null,
            },
          }
        : {}),
      ...(audience === "specific" ? { user_ids: selectedIds } : {}),
    };

    startTransition(async () => {
      const res = await action(input);
      if (res.ok) {
        setErrors({});
        const data = res.data;
        toast({
          tone: "ok",
          title: res.message ?? t("result.queuedTitle"),
          body: data
            ? t("result.summary", {
                queued: data.queued.total,
                skipped: data.skipped.total,
              })
            : undefined,
        });
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("result.failedTitle"), body: res.message });
      }
    });
  };

  const audienceItems = [
    { id: "all", label: t("audience.all") },
    ...(segmentFields.length > 0
      ? [{ id: "segment", label: t("audience.segment") }]
      : []),
    { id: "specific", label: t("audience.specific") },
  ];

  return (
    <div className="stack" style={{ gap: 18 }}>
      {/* Channels */}
      <div className="card card-pad">
        <div className="stack" style={{ gap: 12 }}>
          <div>
            <h3 className="h3">{t("channels.heading")}</h3>
            <p className="muted" style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
              {t("channels.description")}
            </p>
          </div>
          <div className="row" style={{ gap: 18 }}>
            <Checkbox
              checked={wantsEmail}
              onChange={() => toggleChannel("email")}
            >
              {t("channels.email")}
            </Checkbox>
            <Checkbox checked={wantsSms} onChange={() => toggleChannel("sms")}>
              {t("channels.sms")}
            </Checkbox>
          </div>
          {errors.channels && (
            <span className="hint" style={{ color: "var(--danger)" }}>
              {errors.channels[0]}
            </span>
          )}
        </div>
      </div>

      {/* Audience */}
      <div className="card card-pad">
        <div className="stack" style={{ gap: 14 }}>
          <div>
            <h3 className="h3">{t("audience.heading")}</h3>
            <p className="muted" style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
              {t("audience.description")}
            </p>
          </div>

          <Segmented
            items={audienceItems}
            value={audience}
            onChange={(id) => setAudience(id as BroadcastAudience)}
          />

          {audience === "segment" && segmentFields.length > 0 && (
            <div className="grid2">
              {segmentFields.map((sf) => (
                <Field key={sf.key} label={sf.label}>
                  <Select
                    value={segment[sf.key] ?? ""}
                    onChange={(e) =>
                      setSegment((prev) => ({ ...prev, [sf.key]: e.target.value }))
                    }
                  >
                    <option value="">{t("segment.any")}</option>
                    {sf.options.map((opt) => (
                      <option key={opt.value} value={opt.value}>
                        {opt.label}
                      </option>
                    ))}
                  </Select>
                </Field>
              ))}
            </div>
          )}

          {audience === "specific" && (
            <div className="stack" style={{ gap: 10 }}>
              <Input
                icon="search"
                placeholder={t("specific.searchPlaceholder")}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
              {errors.user_ids && (
                <span className="hint" style={{ color: "var(--danger)" }}>
                  {errors.user_ids[0]}
                </span>
              )}
              <div
                className="stack"
                style={{
                  gap: 6,
                  maxHeight: 280,
                  overflowY: "auto",
                  border: "1px solid var(--border)",
                  borderRadius: "var(--radius)",
                  padding: 10,
                }}
              >
                {filteredRecipients.length === 0 ? (
                  <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
                    {t("specific.empty")}
                  </p>
                ) : (
                  filteredRecipients.map((r) => (
                    <Checkbox
                      key={r.id}
                      checked={selectedIds.includes(r.id)}
                      onChange={() => toggleRecipient(r.id)}
                    >
                      <span>
                        {r.name}
                        {r.email ? (
                          <span className="muted"> · {r.email}</span>
                        ) : null}
                      </span>
                    </Checkbox>
                  ))
                )}
              </div>
              <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
                {t("specific.selectedCount", { count: selectedIds.length })}
              </p>
            </div>
          )}

          {audience !== "specific" && (
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("audience.countHint", { count: recipientHint })}
            </p>
          )}
        </div>
      </div>

      {/* Message */}
      <div className="card card-pad">
        <div className="stack" style={{ gap: 14 }}>
          <div>
            <h3 className="h3">{t("message.heading")}</h3>
            <p className="muted" style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
              {t("message.description")}
            </p>
          </div>

          {wantsEmail && (
            <Field
              label={t("message.subject")}
              error={errors.subject?.[0]}
            >
              <Input
                placeholder={t("message.subjectPlaceholder")}
                value={subject}
                onChange={(e) => setSubject(e.target.value)}
              />
            </Field>
          )}

          <Field
            label={t("message.body")}
            hint={wantsSms ? t("message.smsHint") : undefined}
            error={errors.body?.[0]}
          >
            <Textarea
              rows={7}
              placeholder={t("message.bodyPlaceholder")}
              value={body}
              onChange={(e) => setBody(e.target.value)}
            />
          </Field>
        </div>
      </div>

      <Button
        variant="primary"
        icon="send"
        loading={pending}
        onClick={submit}
        style={{ alignSelf: "flex-start" }}
      >
        {pending ? t("submitSending") : t("submit")}
      </Button>
    </div>
  );
}
