"use client";

import { useTranslations } from "next-intl";
import { useState, useTransition } from "react";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { FileDropzone } from "@/components/ui/FileDropzone";
import { Input } from "@/components/ui/Input";
import { Tabs } from "@/components/ui/Tabs";
import { useImageCropper } from "@/components/ui/useImageCropper";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { updateAbout, uploadAboutImage } from "@/lib/actions/admin";
import type { AboutContent, LocalizedText } from "@/lib/types";
import type { AboutStat, AboutValue } from "@/lib/types/content";

/** The About page renders exactly 3 hero stats and 4 value cards. */
const STAT_COUNT = 3;
const VALUE_COUNT = 4;

/* -------------------------------------------------------------------------- */
/*  Local form state                                                          */
/*                                                                            */
/*  Mirrors the wire schema, but normalises every optional object to a        */
/*  concrete one (empty strings/arrays instead of `undefined`) so controlled  */
/*  inputs never flip between controlled/uncontrolled. The payload is pruned  */
/*  on save (see `buildPayload`) so empties stay out of the saved JSON.       */
/* -------------------------------------------------------------------------- */

interface TextPair {
  ar: string;
  en: string;
}

/**
 * A per-section image: the canonical storage `path` (round-tripped on save)
 * and a resolved `url` used only for the preview. Both empty means "no image".
 */
interface ImageState {
  path: string;
  url: string;
}

interface HeroState {
  eyebrow: TextPair;
  title1: TextPair;
  title2: TextPair;
  lead: TextPair;
  image: ImageState;
  imageSecondary: ImageState;
}

interface StatState {
  value: string;
  label: TextPair;
}

interface CopyState {
  title: TextPair;
  body: TextPair;
}

interface ValueState {
  title: TextPair;
  body: TextPair;
}

interface SectionState {
  /** Stable key so React keeps input focus across add/remove. */
  key: string;
  heading: TextPair;
  body: TextPair;
  image: ImageState;
}

interface FormState {
  hero: HeroState;
  stats: StatState[];
  mission: CopyState;
  vision: CopyState;
  valuesTitle: TextPair;
  values: ValueState[];
  sections: SectionState[];
  cta: CopyState;
}

const emptyPair = (): TextPair => ({ ar: "", en: "" });

function toPair(value?: LocalizedText): TextPair {
  return { ar: value?.ar ?? "", en: value?.en ?? "" };
}

const emptyImage = (): ImageState => ({ path: "", url: "" });

/** Seed an image control from the stored path + backend-resolved display URL. */
function toImage(path?: string, url?: string): ImageState {
  return { path: path ?? "", url: url ?? "" };
}

/** Monotonic id source for newly-added repeatable sections. */
let sectionKeySeq = 0;
const nextSectionKey = (): string => `section-${sectionKeySeq++}`;

/**
 * Seed a fixed-count list (3 stats / 4 values) from `initial`, padding with
 * empty slots so the editor always renders the same number of rows.
 */
function fixedList<T>(items: T[] | undefined, count: number, make: () => T): T[] {
  const seeded = (items ?? []).slice(0, count);
  while (seeded.length < count) seeded.push(make());
  return seeded;
}

function buildInitial(c: AboutContent): FormState {
  return {
    hero: {
      eyebrow: toPair(c.eyebrow),
      title1: toPair(c.title1),
      title2: toPair(c.title2),
      lead: toPair(c.lead),
      image: toImage(c.image, c.imageUrl),
      imageSecondary: toImage(c.imageSecondary, c.imageSecondaryUrl),
    },
    stats: fixedList<StatState>(
      (c.stats ?? []).map((s: AboutStat) => ({
        value: s.value ?? "",
        label: toPair(s.label),
      })),
      STAT_COUNT,
      () => ({ value: "", label: emptyPair() }),
    ),
    mission: {
      title: toPair(c.mission?.title),
      body: toPair(c.mission?.body),
    },
    vision: {
      title: toPair(c.vision?.title),
      body: toPair(c.vision?.body),
    },
    valuesTitle: toPair(c.valuesTitle),
    values: fixedList<ValueState>(
      (c.values ?? []).map((v: AboutValue) => ({
        title: toPair(v.title),
        body: toPair(v.body),
      })),
      VALUE_COUNT,
      () => ({ title: emptyPair(), body: emptyPair() }),
    ),
    sections: (c.sections ?? []).map((s) => ({
      key: nextSectionKey(),
      heading: toPair(s.heading),
      body: toPair(s.body),
      image: toImage(s.image, s.imageUrl),
    })),
    cta: {
      title: toPair(c.cta?.title),
      body: toPair(c.cta?.body),
    },
  };
}

/* -------------------------------------------------------------------------- */
/*  Payload builder — prune empties + strip every resolved `*Url` key         */
/* -------------------------------------------------------------------------- */

/** The stored image path, or `undefined` when no image is set. */
function imagePath(image: ImageState): string | undefined {
  const path = image.path.trim();
  return path ? path : undefined;
}

/** A `{ar,en}` pair → `LocalizedText`, or `undefined` when both sides empty. */
function pairToText(pair: TextPair): LocalizedText | undefined {
  const ar = pair.ar.trim();
  const en = pair.en.trim();
  if (!ar && !en) return undefined;
  const out: LocalizedText = {};
  if (ar) out.ar = ar;
  if (en) out.en = en;
  return out;
}

/** Drop keys whose value is `undefined`; return `undefined` if nothing is left. */
function compact<T extends Record<string, unknown>>(obj: T): T | undefined {
  const entries = Object.entries(obj).filter(([, v]) => v !== undefined);
  return entries.length ? (Object.fromEntries(entries) as T) : undefined;
}

/**
 * Build the wire payload. Empty `{ar,en}` pairs and empty strings are pruned,
 * and no `*Url` key is ever written — only canonical `image` paths survive, so
 * the backend-resolved display URLs never round-trip back into storage.
 */
function buildPayload(form: FormState): AboutContent {
  const mission = compact({
    title: pairToText(form.mission.title),
    body: pairToText(form.mission.body),
  });
  const vision = compact({
    title: pairToText(form.vision.title),
    body: pairToText(form.vision.body),
  });
  const cta = compact({
    title: pairToText(form.cta.title),
    body: pairToText(form.cta.body),
  });

  const stats = form.stats
    .map((s) => {
      const label = pairToText(s.label);
      const value = s.value.trim();
      if (!value && !label) return undefined;
      return compact({ value: value || undefined, label });
    })
    .filter((s): s is NonNullable<typeof s> => s !== undefined);

  const values = form.values
    .map((v) =>
      compact({ title: pairToText(v.title), body: pairToText(v.body) }),
    )
    .filter((v): v is NonNullable<typeof v> => v !== undefined);

  const sections = form.sections
    .map((s) =>
      compact({
        heading: pairToText(s.heading),
        body: pairToText(s.body),
        image: imagePath(s.image),
      }),
    )
    .filter((s): s is NonNullable<typeof s> => s !== undefined);

  const eyebrow = pairToText(form.hero.eyebrow);
  const title1 = pairToText(form.hero.title1);
  const title2 = pairToText(form.hero.title2);
  const lead = pairToText(form.hero.lead);
  const image = imagePath(form.hero.image);
  const imageSecondary = imagePath(form.hero.imageSecondary);
  const valuesTitle = pairToText(form.valuesTitle);

  return {
    ...(eyebrow ? { eyebrow } : {}),
    ...(title1 ? { title1 } : {}),
    ...(title2 ? { title2 } : {}),
    ...(lead ? { lead } : {}),
    ...(stats.length ? { stats } : {}),
    ...(mission ? { mission } : {}),
    ...(vision ? { vision } : {}),
    ...(valuesTitle ? { valuesTitle } : {}),
    ...(values.length ? { values } : {}),
    ...(sections.length ? { sections } : {}),
    ...(cta ? { cta } : {}),
    ...(image ? { image } : {}),
    ...(imageSecondary ? { imageSecondary } : {}),
  };
}

/* -------------------------------------------------------------------------- */
/*  Reusable bilingual field                                                  */
/* -------------------------------------------------------------------------- */

interface BilingualLabels {
  ar: string;
  en: string;
}

interface BilingualFieldProps {
  label: string;
  value: TextPair;
  onChange: (pair: TextPair) => void;
  labels: BilingualLabels;
  multiline?: boolean;
}

/** A labelled pair of Arabic + English inputs editing one `{ar,en}` value. */
function BilingualField({
  label,
  value,
  onChange,
  labels,
  multiline,
}: BilingualFieldProps) {
  return (
    <div className="stack" style={{ gap: 8 }}>
      <span className="label">{label}</span>
      <div className="grid2">
        {(["ar", "en"] as const).map((lang) => (
          <Field key={lang} label={labels[lang]}>
            {multiline ? (
              <Textarea
                rows={3}
                dir={lang === "ar" ? "rtl" : "ltr"}
                value={value[lang]}
                onChange={(e) => onChange({ ...value, [lang]: e.target.value })}
              />
            ) : (
              <Input
                dir={lang === "ar" ? "rtl" : "ltr"}
                value={value[lang]}
                onChange={(e) => onChange({ ...value, [lang]: e.target.value })}
              />
            )}
          </Field>
        ))}
      </div>
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Section image control                                                     */
/* -------------------------------------------------------------------------- */

interface SectionImageLabels {
  label: string;
  hint: string;
  upload: string;
  replace: string;
  remove: string;
}

interface SectionImageFieldProps {
  value: ImageState;
  onSelect: (file: File | null) => void;
  uploading: boolean;
  labels: SectionImageLabels;
}

/**
 * A per-section image control: a dropzone to upload/replace plus a live preview
 * with a Remove button. Clearing it (Remove) drops the image so the public page
 * falls back to its built-in default illustration. Presentational only — the
 * parent owns the upload + state.
 */
function SectionImageField({
  value,
  onSelect,
  uploading,
  labels,
}: SectionImageFieldProps) {
  const hasImage = value.url.trim() !== "";
  // About illustrations are landscape → crop to 16:9 before uploading.
  // Clearing (file === null) bypasses the cropper and removes the image.
  const { cropFile, cropper } = useImageCropper({ aspect: 16 / 9, maxSize: 1600 });

  const handleSelect = async (file: File | null) => {
    if (file === null) {
      onSelect(null);
      return;
    }
    const cropped = await cropFile(file);
    if (cropped) onSelect(cropped);
  };

  return (
    <div className="stack" style={{ gap: 8 }}>
      {cropper}
      <span className="label">{labels.label}</span>
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {labels.hint}
      </p>
      {hasImage && (
        <div className="stack" style={{ gap: 8 }}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={value.url}
            alt=""
            style={{
              width: "100%",
              maxWidth: 360,
              height: 160,
              objectFit: "cover",
              borderRadius: "var(--r-lg)",
              display: "block",
              border: "1px solid var(--line)",
            }}
          />
          <Button
            variant="ghost"
            icon="trash"
            onClick={() => onSelect(null)}
            disabled={uploading}
            style={{ alignSelf: "flex-start" }}
          >
            {labels.remove}
          </Button>
        </div>
      )}
      <FileDropzone
        value={null}
        onChange={handleSelect}
        accept="image/png,image/jpeg,image/jpg,image/webp"
        label={hasImage ? labels.replace : labels.upload}
        disabled={uploading}
      />
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Editor                                                                    */
/* -------------------------------------------------------------------------- */

export interface AboutEditorProps {
  initial: AboutContent;
}

/**
 * Which image slot is currently uploading, so its dropzone can show a pending
 * state. Hero slots are fixed keys; per-section images are addressed by index.
 */
type ImageSlot =
  | { kind: "hero" }
  | { kind: "heroSecondary" }
  | { kind: "section"; index: number };

function sameSlot(a: ImageSlot | null, b: ImageSlot): boolean {
  if (!a || a.kind !== b.kind) return false;
  if (a.kind === "section" && b.kind === "section") return a.index === b.index;
  return true;
}

export function AboutEditor({ initial }: AboutEditorProps) {
  const t = useTranslations("admin.about");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [tab, setTab] = useState("hero");
  const [form, setForm] = useState<FormState>(() => buildInitial(initial));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [uploading, setUploading] = useState<ImageSlot | null>(null);

  const labels: BilingualLabels = { ar: t("labelAr"), en: t("labelEn") };

  const imageShared = {
    hint: t("image.hint"),
    upload: t("image.upload"),
    replace: t("image.replace"),
    remove: t("image.remove"),
  };
  const imageLabelsMain: SectionImageLabels = {
    label: t("image.labelMain"),
    ...imageShared,
  };
  const imageLabelsSecondary: SectionImageLabels = {
    label: t("image.labelSecondary"),
    ...imageShared,
  };
  const imageLabelsSection: SectionImageLabels = {
    label: t("image.labelSection"),
    ...imageShared,
  };

  /* ---- hero text ---- */
  const setHero = (key: keyof HeroState, pair: TextPair) =>
    setForm((f) => ({ ...f, hero: { ...f.hero, [key]: pair } }));

  /* ---- images (hero + per-section) ---- */
  const writeImage = (slot: ImageSlot, image: ImageState) =>
    setForm((f) => {
      switch (slot.kind) {
        case "hero":
          return { ...f, hero: { ...f.hero, image } };
        case "heroSecondary":
          return { ...f, hero: { ...f.hero, imageSecondary: image } };
        case "section":
          return {
            ...f,
            sections: f.sections.map((s, i) =>
              i === slot.index ? { ...s, image } : s,
            ),
          };
      }
    });

  // Upload a dropped file to the media disk, then store the returned path (for
  // saving) and url (for preview). A failed upload is toasted and leaves the
  // current image untouched.
  const uploadImage = (slot: ImageSlot, file: File | null) => {
    if (file === null) {
      writeImage(slot, emptyImage());
      return;
    }
    setUploading(slot);
    startTransition(async () => {
      const res = await uploadAboutImage(file);
      setUploading(null);
      if (res.ok) {
        writeImage(slot, { path: res.data.path, url: res.data.url });
      } else {
        toast({ tone: "err", title: t("image.uploadFailed"), body: res.message });
      }
    });
  };

  /* ---- stats (fixed 3) ---- */
  const setStat = (index: number, patch: Partial<StatState>) =>
    setForm((f) => ({
      ...f,
      stats: f.stats.map((s, i) => (i === index ? { ...s, ...patch } : s)),
    }));

  /* ---- mission / vision / cta ---- */
  const setCopy = (key: "mission" | "vision" | "cta", patch: Partial<CopyState>) =>
    setForm((f) => ({ ...f, [key]: { ...f[key], ...patch } }));

  /* ---- values (fixed 4) ---- */
  const setValue = (index: number, patch: Partial<ValueState>) =>
    setForm((f) => ({
      ...f,
      values: f.values.map((v, i) => (i === index ? { ...v, ...patch } : v)),
    }));

  /* ---- sections (repeatable) ---- */
  const setSection = (index: number, patch: Partial<SectionState>) =>
    setForm((f) => ({
      ...f,
      sections: f.sections.map((s, i) => (i === index ? { ...s, ...patch } : s)),
    }));

  const addSection = () =>
    setForm((f) => ({
      ...f,
      sections: [
        ...f.sections,
        {
          key: nextSectionKey(),
          heading: emptyPair(),
          body: emptyPair(),
          image: emptyImage(),
        },
      ],
    }));

  const removeSection = (index: number) =>
    setForm((f) => ({
      ...f,
      sections: f.sections.filter((_, i) => i !== index),
    }));

  const save = () => {
    const payload = buildPayload(form);
    startTransition(async () => {
      const res = await updateAbout(payload);
      if (res.ok) {
        setErrors({});
        toast({ tone: "ok", title: t("saved") });
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("saveFailed"), body: res.message });
      }
    });
  };

  const tabs = [
    { id: "hero", label: t("tabHero") },
    { id: "missionVision", label: t("tabMissionVision") },
    { id: "values", label: t("tabValues") },
    { id: "sections", label: t("tabSections") },
    { id: "cta", label: t("tabCta") },
  ];

  return (
    <div className="le-editor stack" style={{ gap: 18 }}>
      <Tabs items={tabs} value={tab} onChange={setTab} />

      <div className="card card-pad">
        {tab === "hero" && (
          <div className="stack" style={{ gap: 18 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("hero.hint")}
            </p>
            <BilingualField
              labels={labels}
              label={t("hero.fieldEyebrow")}
              value={form.hero.eyebrow}
              onChange={(p) => setHero("eyebrow", p)}
            />
            <BilingualField
              labels={labels}
              label={t("hero.fieldTitle1")}
              value={form.hero.title1}
              onChange={(p) => setHero("title1", p)}
            />
            <BilingualField
              labels={labels}
              label={t("hero.fieldTitle2")}
              value={form.hero.title2}
              onChange={(p) => setHero("title2", p)}
            />
            <BilingualField
              labels={labels}
              label={t("hero.fieldLead")}
              value={form.hero.lead}
              onChange={(p) => setHero("lead", p)}
              multiline
            />
            <SectionImageField
              labels={imageLabelsMain}
              value={form.hero.image}
              uploading={sameSlot(uploading, { kind: "hero" })}
              onSelect={(file) => uploadImage({ kind: "hero" }, file)}
            />
            <SectionImageField
              labels={imageLabelsSecondary}
              value={form.hero.imageSecondary}
              uploading={sameSlot(uploading, { kind: "heroSecondary" })}
              onSelect={(file) => uploadImage({ kind: "heroSecondary" }, file)}
            />

            <div className="stack" style={{ gap: 16 }}>
              <span className="label">{t("stats.label")}</span>
              <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
                {t("stats.hint")}
              </p>
              {form.stats.map((stat, i) => (
                <div key={i} className="card card-pad stack" style={{ gap: 12 }}>
                  <Field label={t("stats.value")}>
                    <Input
                      className="ltr"
                      value={stat.value}
                      onChange={(e) => setStat(i, { value: e.target.value })}
                    />
                  </Field>
                  <BilingualField
                    labels={labels}
                    label={t("stats.labelField")}
                    value={stat.label}
                    onChange={(p) => setStat(i, { label: p })}
                  />
                </div>
              ))}
            </div>
          </div>
        )}

        {tab === "missionVision" && (
          <div className="stack" style={{ gap: 18 }}>
            <div className="stack" style={{ gap: 12 }}>
              <span className="label">{t("mission.heading")}</span>
              <BilingualField
                labels={labels}
                label={t("mission.fieldTitle")}
                value={form.mission.title}
                onChange={(p) => setCopy("mission", { title: p })}
              />
              <BilingualField
                labels={labels}
                label={t("mission.fieldBody")}
                value={form.mission.body}
                onChange={(p) => setCopy("mission", { body: p })}
                multiline
              />
            </div>
            <div className="stack" style={{ gap: 12 }}>
              <span className="label">{t("vision.heading")}</span>
              <BilingualField
                labels={labels}
                label={t("vision.fieldTitle")}
                value={form.vision.title}
                onChange={(p) => setCopy("vision", { title: p })}
              />
              <BilingualField
                labels={labels}
                label={t("vision.fieldBody")}
                value={form.vision.body}
                onChange={(p) => setCopy("vision", { body: p })}
                multiline
              />
            </div>
          </div>
        )}

        {tab === "values" && (
          <div className="stack" style={{ gap: 18 }}>
            <BilingualField
              labels={labels}
              label={t("values.fieldTitle")}
              value={form.valuesTitle}
              onChange={(p) => setForm((f) => ({ ...f, valuesTitle: p }))}
            />
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("values.hint")}
            </p>
            {form.values.map((value, i) => (
              <div key={i} className="card card-pad stack" style={{ gap: 12 }}>
                <span className="label">#{i + 1}</span>
                <BilingualField
                  labels={labels}
                  label={t("values.itemTitle")}
                  value={value.title}
                  onChange={(p) => setValue(i, { title: p })}
                />
                <BilingualField
                  labels={labels}
                  label={t("values.itemBody")}
                  value={value.body}
                  onChange={(p) => setValue(i, { body: p })}
                  multiline
                />
              </div>
            ))}
          </div>
        )}

        {tab === "sections" && (
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("sections.hint")}
            </p>
            {form.sections.length === 0 && (
              <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                {t("sections.empty")}
              </p>
            )}
            {form.sections.map((section, i) => (
              <div
                key={section.key}
                className="card card-pad stack"
                style={{ gap: 12 }}
              >
                <div className="between">
                  <span className="label">#{i + 1}</span>
                  <Button
                    variant="ghost"
                    icon="trash"
                    onClick={() => removeSection(i)}
                    aria-label={t("sections.remove")}
                  />
                </div>
                <BilingualField
                  labels={labels}
                  label={t("sections.heading")}
                  value={section.heading}
                  onChange={(p) => setSection(i, { heading: p })}
                />
                <BilingualField
                  labels={labels}
                  label={t("sections.body")}
                  value={section.body}
                  onChange={(p) => setSection(i, { body: p })}
                  multiline
                />
                <SectionImageField
                  labels={imageLabelsSection}
                  value={section.image}
                  uploading={sameSlot(uploading, { kind: "section", index: i })}
                  onSelect={(file) =>
                    uploadImage({ kind: "section", index: i }, file)
                  }
                />
              </div>
            ))}
            <Button
              variant="secondary"
              icon="plus"
              onClick={addSection}
              style={{ alignSelf: "flex-start" }}
            >
              {t("sections.add")}
            </Button>
          </div>
        )}

        {tab === "cta" && (
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("cta.hint")}
            </p>
            <BilingualField
              labels={labels}
              label={t("cta.fieldTitle")}
              value={form.cta.title}
              onChange={(p) => setCopy("cta", { title: p })}
            />
            <BilingualField
              labels={labels}
              label={t("cta.fieldBody")}
              value={form.cta.body}
              onChange={(p) => setCopy("cta", { body: p })}
              multiline
            />
          </div>
        )}
      </div>

      {Object.keys(errors).length > 0 && (
        <div className="field is-error stack" style={{ gap: 4 }}>
          {Object.values(errors)
            .flat()
            .map((message) => (
              <span key={message} className="hint">
                {message}
              </span>
            ))}
        </div>
      )}

      <Button
        variant="primary"
        icon="check"
        loading={pending}
        onClick={save}
        style={{ alignSelf: "flex-start" }}
      >
        {pending ? t("saving") : t("save")}
      </Button>
    </div>
  );
}
