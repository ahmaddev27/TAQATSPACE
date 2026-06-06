"use client";

import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState, useTransition, type ReactNode } from "react";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { FileDropzone } from "@/components/ui/FileDropzone";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Tabs } from "@/components/ui/Tabs";
import { useImageCropper } from "@/components/ui/useImageCropper";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { updateLanding, uploadLandingImage } from "@/lib/actions/admin";
import type {
  LandingContent,
  LocalizedText,
  ReorderableSectionKey,
} from "@/lib/types";
import { sanitizeSectionsOrder } from "@/lib/types";

const MAX_STATS = 4;
const MAX_TESTIMONIALS = 12;

/** Minimal workspace shape the editor needs to render the "featured" picker. */
export interface WorkspaceOption {
  id: string;
  name: string;
}

export interface LandingEditorProps {
  initial: LandingContent;
  workspaces: WorkspaceOption[];
}

/* -------------------------------------------------------------------------- */
/*  Local form state                                                          */
/*                                                                            */
/*  We mirror the wire schema closely, but normalise every optional object to */
/*  a concrete one (empty strings instead of `undefined`) so the controlled   */
/*  inputs never flip between controlled/uncontrolled. The payload is then    */
/*  pruned on save so empties stay out of the JSON where it keeps it clean.   */
/* -------------------------------------------------------------------------- */

interface TextPair {
  ar: string;
  en: string;
}

/**
 * A per-section image: the canonical storage `path` (round-tripped on save) and
 * a resolved `url` used only for the preview. Both empty means "no override".
 */
interface ImageState {
  path: string;
  url: string;
}

/** The sections whose imagery the admin can override. */
type ImageSectionKey = "hero" | "why";

interface HeroState {
  title: TextPair;
  highlight: TextPair;
  subtitle: TextPair;
  ctaPrimary: TextPair;
  ctaSecondary: TextPair;
  image: ImageState;
}

interface StatState {
  value: string;
  label: TextPair;
}

interface FeaturedState {
  enabled: boolean;
  title: TextPair;
  subtitle: TextPair;
  workspaceIds: string[];
}

interface WhyState {
  enabled: boolean;
  title: TextPair;
  highlight: TextPair;
  subtitle: TextPair;
  image: ImageState;
}

interface CapabilitiesState {
  enabled: boolean;
  title: TextPair;
  subtitle: TextPair;
}

interface TestimonialsSectionState {
  enabled: boolean;
  title: TextPair;
}

interface TestimonialState {
  text: TextPair;
  name: string;
  role: TextPair;
}

interface FormState {
  hero: HeroState;
  stats: StatState[];
  featured: FeaturedState;
  why: WhyState;
  capabilities: CapabilitiesState;
  testimonialsSection: TestimonialsSectionState;
  testimonials: TestimonialState[];
  /** Render order of the reorderable sections (always all four, de-duped). */
  sectionsOrder: ReorderableSectionKey[];
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

function buildInitial(c: LandingContent): FormState {
  return {
    hero: {
      title: toPair(c.hero?.title),
      highlight: toPair(c.hero?.highlight),
      subtitle: toPair(c.hero?.subtitle),
      ctaPrimary: toPair(c.hero?.ctaPrimary),
      ctaSecondary: toPair(c.hero?.ctaSecondary),
      image: toImage(c.hero?.image, c.hero?.imageUrl),
    },
    stats: (c.stats ?? []).slice(0, MAX_STATS).map((s) => ({
      value: s.value ?? "",
      label: toPair(s.label),
    })),
    featured: {
      enabled: c.sections?.featured?.enabled ?? true,
      title: toPair(c.sections?.featured?.title),
      subtitle: toPair(c.sections?.featured?.subtitle),
      workspaceIds: c.sections?.featured?.workspaceIds ?? [],
    },
    why: {
      enabled: c.sections?.why?.enabled ?? true,
      title: toPair(c.sections?.why?.title),
      highlight: toPair(c.sections?.why?.highlight),
      subtitle: toPair(c.sections?.why?.subtitle),
      image: toImage(c.sections?.why?.image, c.sections?.why?.imageUrl),
    },
    capabilities: {
      enabled: c.sections?.capabilities?.enabled ?? true,
      title: toPair(c.sections?.capabilities?.title),
      subtitle: toPair(c.sections?.capabilities?.subtitle),
    },
    testimonialsSection: {
      enabled: c.sections?.testimonials?.enabled ?? true,
      title: toPair(c.sections?.testimonials?.title),
    },
    testimonials: (c.testimonials ?? []).slice(0, MAX_TESTIMONIALS).map((m) => ({
      text: toPair(m.text),
      name: m.name ?? "",
      role: toPair(m.role),
    })),
    sectionsOrder: sanitizeSectionsOrder(c.sections_order),
  };
}

/* -------------------------------------------------------------------------- */
/*  Payload builder — prune empties so the saved JSON stays clean             */
/* -------------------------------------------------------------------------- */

/** The stored image path, or `undefined` when no image is set for the section. */
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

function buildPayload(form: FormState): LandingContent {
  const hero = compact({
    title: pairToText(form.hero.title),
    highlight: pairToText(form.hero.highlight),
    subtitle: pairToText(form.hero.subtitle),
    ctaPrimary: pairToText(form.hero.ctaPrimary),
    ctaSecondary: pairToText(form.hero.ctaSecondary),
    image: imagePath(form.hero.image),
  });

  const stats = form.stats
    .map((s) => {
      const label = pairToText(s.label);
      const value = s.value.trim();
      if (!value && !label) return undefined;
      return { value, label };
    })
    .filter((s): s is NonNullable<typeof s> => s !== undefined);

  const featured = {
    enabled: form.featured.enabled,
    title: pairToText(form.featured.title),
    subtitle: pairToText(form.featured.subtitle),
    workspaceIds: form.featured.workspaceIds,
  };

  const why = {
    enabled: form.why.enabled,
    title: pairToText(form.why.title),
    highlight: pairToText(form.why.highlight),
    subtitle: pairToText(form.why.subtitle),
    image: imagePath(form.why.image),
  };

  const capabilities = {
    enabled: form.capabilities.enabled,
    title: pairToText(form.capabilities.title),
    subtitle: pairToText(form.capabilities.subtitle),
  };

  const testimonialsSection = {
    enabled: form.testimonialsSection.enabled,
    title: pairToText(form.testimonialsSection.title),
  };

  const testimonials = form.testimonials
    .map((m) => {
      const text = pairToText(m.text);
      const name = m.name.trim();
      const role = pairToText(m.role);
      if (!text && !name && !role) return undefined;
      return { text, name, role };
    })
    .filter((m): m is NonNullable<typeof m> => m !== undefined);

  return {
    ...(hero ? { hero } : {}),
    ...(stats.length ? { stats } : {}),
    sections: { featured, why, capabilities, testimonials: testimonialsSection },
    ...(testimonials.length ? { testimonials } : {}),
    sections_order: form.sectionsOrder,
  };
}

/* -------------------------------------------------------------------------- */
/*  Live-preview projection                                                   */
/* -------------------------------------------------------------------------- */

/** A reorderable section's title/subtitle pairs, pulled from the form state. */
function sectionContent(
  form: FormState,
  key: ReorderableSectionKey,
): { title: TextPair; subtitle: TextPair } {
  switch (key) {
    case "featured":
      return { title: form.featured.title, subtitle: form.featured.subtitle };
    case "why":
      return { title: form.why.title, subtitle: form.why.subtitle };
    case "capabilities":
      return {
        title: form.capabilities.title,
        subtitle: form.capabilities.subtitle,
      };
    case "testimonials":
      return {
        title: form.testimonialsSection.title,
        subtitle: emptyPair(),
      };
  }
}

/** One row in the mini live preview. */
interface PreviewSection {
  key: ReorderableSectionKey;
  name: string;
  title: string;
  subtitle: string;
  enabled: boolean;
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
  /** Title for the whole control, e.g. "Section image". */
  label: string;
  /** Helper text under the title (recommended size, fallback note). */
  hint: string;
  /** Dropzone call-to-action when no image is set. */
  upload: string;
  /** Button/label to replace the current image. */
  replace: string;
  /** Button to clear the override and fall back to the default. */
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
 * with a Remove button. Clearing it (Remove) drops the override so the public
 * page falls back to its built-in default illustration. Presentational only —
 * the parent owns the upload + state.
 */
function SectionImageField({
  value,
  onSelect,
  uploading,
  labels,
}: SectionImageFieldProps) {
  const hasImage = value.url.trim() !== "";
  // Landing illustrations are landscape → crop to 16:9 before uploading.
  // Clearing (file === null) bypasses the cropper and removes the override.
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

export function LandingEditor({ initial, workspaces }: LandingEditorProps) {
  const t = useTranslations("admin.landing");
  const locale = useLocale();
  const lang: "ar" | "en" = locale === "en" ? "en" : "ar";
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [tab, setTab] = useState("hero");
  const [form, setForm] = useState<FormState>(() => buildInitial(initial));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  // Which section's image is currently uploading (so its dropzone can show a
  // pending state). `null` when no upload is in flight.
  const [uploading, setUploading] = useState<ImageSectionKey | null>(null);

  // Localised labels for the Arabic/English sub-inputs, passed to each
  // module-level `BilingualField` so no component is created during render.
  const labels: BilingualLabels = { ar: t("labelAr"), en: t("labelEn") };

  // Localised labels for the per-section image control (shared by hero + why).
  const imageLabels: SectionImageLabels = {
    label: t("image.label"),
    hint: t("image.hint"),
    upload: t("image.upload"),
    replace: t("image.replace"),
    remove: t("image.remove"),
  };

  const setHero = (key: keyof HeroState, pair: TextPair) =>
    setForm((f) => ({ ...f, hero: { ...f.hero, [key]: pair } }));

  // Write an image (uploaded or cleared) into the right section's form slice.
  const setSectionImage = (key: ImageSectionKey, image: ImageState) =>
    setForm((f) =>
      key === "hero"
        ? { ...f, hero: { ...f.hero, image } }
        : { ...f, why: { ...f.why, image } },
    );

  // Upload a dropped file to the media disk, then store the returned path (for
  // saving) and url (for preview) on the section. A failed upload is toasted
  // and leaves the current image untouched.
  const uploadImage = (key: ImageSectionKey, file: File | null) => {
    if (file === null) {
      setSectionImage(key, emptyImage());
      return;
    }
    setUploading(key);
    startTransition(async () => {
      const res = await uploadLandingImage(file);
      setUploading(null);
      if (res.ok) {
        setSectionImage(key, { path: res.data.path, url: res.data.url });
      } else {
        toast({ tone: "err", title: t("image.uploadFailed"), body: res.message });
      }
    });
  };

  const setStat = (index: number, patch: Partial<StatState>) =>
    setForm((f) => ({
      ...f,
      stats: f.stats.map((s, i) => (i === index ? { ...s, ...patch } : s)),
    }));

  const addStat = () =>
    setForm((f) =>
      f.stats.length >= MAX_STATS
        ? f
        : { ...f, stats: [...f.stats, { value: "", label: emptyPair() }] },
    );

  const removeStat = (index: number) =>
    setForm((f) => ({ ...f, stats: f.stats.filter((_, i) => i !== index) }));

  const toggleWorkspace = (id: string) =>
    setForm((f) => ({
      ...f,
      featured: {
        ...f.featured,
        workspaceIds: f.featured.workspaceIds.includes(id)
          ? f.featured.workspaceIds.filter((w) => w !== id)
          : [...f.featured.workspaceIds, id],
      },
    }));

  const setTestimonial = (index: number, patch: Partial<TestimonialState>) =>
    setForm((f) => ({
      ...f,
      testimonials: f.testimonials.map((m, i) =>
        i === index ? { ...m, ...patch } : m,
      ),
    }));

  const addTestimonial = () =>
    setForm((f) =>
      f.testimonials.length >= MAX_TESTIMONIALS
        ? f
        : {
            ...f,
            testimonials: [
              ...f.testimonials,
              { text: emptyPair(), name: "", role: emptyPair() },
            ],
          },
    );

  const removeTestimonial = (index: number) =>
    setForm((f) => ({
      ...f,
      testimonials: f.testimonials.filter((_, i) => i !== index),
    }));

  // Move a section one slot up (-1) or down (+1) by swapping array entries.
  const moveSection = (index: number, delta: number) =>
    setForm((f) => {
      const target = index + delta;
      if (target < 0 || target >= f.sectionsOrder.length) return f;
      const next = [...f.sectionsOrder];
      [next[index], next[target]] = [next[target], next[index]];
      return { ...f, sectionsOrder: next };
    });

  // Toggle a section's `enabled` flag from the Layout tab. The four reorderable
  // sections live under different form keys; `testimonials` maps to
  // `testimonialsSection`, the rest map 1:1.
  const setSectionEnabled = (key: ReorderableSectionKey, enabled: boolean) =>
    setForm((f) => {
      switch (key) {
        case "featured":
          return { ...f, featured: { ...f.featured, enabled } };
        case "why":
          return { ...f, why: { ...f.why, enabled } };
        case "capabilities":
          return { ...f, capabilities: { ...f.capabilities, enabled } };
        case "testimonials":
          return {
            ...f,
            testimonialsSection: { ...f.testimonialsSection, enabled },
          };
      }
    });

  const isSectionEnabled = (key: ReorderableSectionKey): boolean => {
    switch (key) {
      case "featured":
        return form.featured.enabled;
      case "why":
        return form.why.enabled;
      case "capabilities":
        return form.capabilities.enabled;
      case "testimonials":
        return form.testimonialsSection.enabled;
    }
  };

  // Localised display name for each reorderable section (reuses the tab labels).
  const sectionName = (key: ReorderableSectionKey): string => {
    switch (key) {
      case "featured":
        return t("tabFeatured");
      case "why":
        return t("tabWhy");
      case "capabilities":
        return t("tabCapabilities");
      case "testimonials":
        return t("tabTestimonials");
    }
  };

  // The Hero + ordered sections, projected to the active locale for the live
  // preview. Recomputed only when the relevant form slices change so typing in
  // an unrelated tab does not rebuild the whole list.
  const previewSections = useMemo<PreviewSection[]>(
    () =>
      form.sectionsOrder.map((key) => {
        const slice = sectionContent(form, key);
        return {
          key,
          name: sectionName(key),
          title: slice.title[lang],
          subtitle: slice.subtitle[lang],
          enabled: isSectionEnabled(key),
        };
      }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [form, lang],
  );

  const save = () => {
    const payload = buildPayload(form);
    startTransition(async () => {
      const res = await updateLanding(payload);
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
    { id: "stats", label: t("tabStats") },
    { id: "featured", label: t("tabFeatured") },
    { id: "why", label: t("tabWhy") },
    { id: "capabilities", label: t("tabCapabilities") },
    { id: "testimonials", label: t("tabTestimonials") },
    { id: "layout", label: t("tabLayout") },
  ];

  const heroTitle = form.hero.title[lang];
  const heroSubtitle = form.hero.subtitle[lang];

  return (
    <div className="le-shell">
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
              label={t("hero.fieldTitle")}
              value={form.hero.title}
              onChange={(p) => setHero("title", p)}
            />
            <BilingualField
              labels={labels}
              label={t("hero.fieldHighlight")}
              value={form.hero.highlight}
              onChange={(p) => setHero("highlight", p)}
            />
            <BilingualField
              labels={labels}
              label={t("hero.fieldSubtitle")}
              value={form.hero.subtitle}
              onChange={(p) => setHero("subtitle", p)}
              multiline
            />
            <BilingualField
              labels={labels}
              label={t("hero.fieldCtaPrimary")}
              value={form.hero.ctaPrimary}
              onChange={(p) => setHero("ctaPrimary", p)}
            />
            <BilingualField
              labels={labels}
              label={t("hero.fieldCtaSecondary")}
              value={form.hero.ctaSecondary}
              onChange={(p) => setHero("ctaSecondary", p)}
            />
            <SectionImageField
              labels={imageLabels}
              value={form.hero.image}
              uploading={uploading === "hero"}
              onSelect={(file) => uploadImage("hero", file)}
            />
          </div>
        )}

        {tab === "stats" && (
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("stats.hint")}
            </p>
            {form.stats.length === 0 && (
              <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                {t("stats.empty")}
              </p>
            )}
            {form.stats.map((stat, i) => (
              <div key={i} className="card card-pad stack" style={{ gap: 12 }}>
                <div className="between">
                  <Field label={t("stats.value")} className="grow">
                    <Input
                      className="ltr"
                      value={stat.value}
                      onChange={(e) => setStat(i, { value: e.target.value })}
                    />
                  </Field>
                  <Button
                    variant="ghost"
                    icon="trash"
                    onClick={() => removeStat(i)}
                    aria-label={t("stats.remove")}
                    style={{ alignSelf: "flex-end" }}
                  />
                </div>
                <BilingualField
              labels={labels}
                  label={t("stats.label")}
                  value={stat.label}
                  onChange={(p) => setStat(i, { label: p })}
                />
              </div>
            ))}
            {form.stats.length < MAX_STATS && (
              <Button
                variant="secondary"
                icon="plus"
                onClick={addStat}
                style={{ alignSelf: "flex-start" }}
              >
                {t("stats.add")}
              </Button>
            )}
          </div>
        )}

        {tab === "featured" && (
          <div className="stack" style={{ gap: 16 }}>
            <SectionToggle
              label={t("enabled")}
              checked={form.featured.enabled}
              onChange={(v) =>
                setForm((f) => ({ ...f, featured: { ...f.featured, enabled: v } }))
              }
            />
            <BilingualField
              labels={labels}
              label={t("featured.fieldTitle")}
              value={form.featured.title}
              onChange={(p) =>
                setForm((f) => ({ ...f, featured: { ...f.featured, title: p } }))
              }
            />
            <BilingualField
              labels={labels}
              label={t("featured.fieldSubtitle")}
              value={form.featured.subtitle}
              onChange={(p) =>
                setForm((f) => ({ ...f, featured: { ...f.featured, subtitle: p } }))
              }
            />
            <div className="stack" style={{ gap: 10 }}>
              <span className="label">{t("featured.pick")}</span>
              <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
                {t("featured.hint")}
              </p>
              {workspaces.length === 0 ? (
                <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                  {t("featured.noWorkspaces")}
                </p>
              ) : (
                <div className="stack" style={{ gap: 8 }}>
                  {workspaces.map((ws) => (
                    <Checkbox
                      key={ws.id}
                      checked={form.featured.workspaceIds.includes(ws.id)}
                      onChange={() => toggleWorkspace(ws.id)}
                    >
                      {ws.name}
                    </Checkbox>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {tab === "why" && (
          <div className="stack" style={{ gap: 16 }}>
            <SectionToggle
              label={t("enabled")}
              checked={form.why.enabled}
              onChange={(v) =>
                setForm((f) => ({ ...f, why: { ...f.why, enabled: v } }))
              }
            />
            <BilingualField
              labels={labels}
              label={t("why.fieldTitle")}
              value={form.why.title}
              onChange={(p) => setForm((f) => ({ ...f, why: { ...f.why, title: p } }))}
            />
            <BilingualField
              labels={labels}
              label={t("why.fieldHighlight")}
              value={form.why.highlight}
              onChange={(p) =>
                setForm((f) => ({ ...f, why: { ...f.why, highlight: p } }))
              }
            />
            <BilingualField
              labels={labels}
              label={t("why.fieldSubtitle")}
              value={form.why.subtitle}
              onChange={(p) =>
                setForm((f) => ({ ...f, why: { ...f.why, subtitle: p } }))
              }
              multiline
            />
            <SectionImageField
              labels={imageLabels}
              value={form.why.image}
              uploading={uploading === "why"}
              onSelect={(file) => uploadImage("why", file)}
            />
          </div>
        )}

        {tab === "capabilities" && (
          <div className="stack" style={{ gap: 16 }}>
            <SectionToggle
              label={t("enabled")}
              checked={form.capabilities.enabled}
              onChange={(v) =>
                setForm((f) => ({
                  ...f,
                  capabilities: { ...f.capabilities, enabled: v },
                }))
              }
            />
            <BilingualField
              labels={labels}
              label={t("capabilities.fieldTitle")}
              value={form.capabilities.title}
              onChange={(p) =>
                setForm((f) => ({
                  ...f,
                  capabilities: { ...f.capabilities, title: p },
                }))
              }
            />
            <BilingualField
              labels={labels}
              label={t("capabilities.fieldSubtitle")}
              value={form.capabilities.subtitle}
              onChange={(p) =>
                setForm((f) => ({
                  ...f,
                  capabilities: { ...f.capabilities, subtitle: p },
                }))
              }
            />
          </div>
        )}

        {tab === "testimonials" && (
          <div className="stack" style={{ gap: 16 }}>
            <SectionToggle
              label={t("enabled")}
              checked={form.testimonialsSection.enabled}
              onChange={(v) =>
                setForm((f) => ({
                  ...f,
                  testimonialsSection: { ...f.testimonialsSection, enabled: v },
                }))
              }
            />
            <BilingualField
              labels={labels}
              label={t("testimonials.fieldTitle")}
              value={form.testimonialsSection.title}
              onChange={(p) =>
                setForm((f) => ({
                  ...f,
                  testimonialsSection: { ...f.testimonialsSection, title: p },
                }))
              }
            />
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("testimonials.hint")}
            </p>
            {form.testimonials.length === 0 && (
              <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                {t("testimonials.empty")}
              </p>
            )}
            {form.testimonials.map((item, i) => (
              <div key={i} className="card card-pad stack" style={{ gap: 12 }}>
                <div className="between">
                  <span className="label">#{i + 1}</span>
                  <Button
                    variant="ghost"
                    icon="trash"
                    onClick={() => removeTestimonial(i)}
                    aria-label={t("testimonials.remove")}
                  />
                </div>
                <BilingualField
              labels={labels}
                  label={t("testimonials.text")}
                  value={item.text}
                  onChange={(p) => setTestimonial(i, { text: p })}
                  multiline
                />
                <Field label={t("testimonials.name")}>
                  <Input
                    value={item.name}
                    onChange={(e) => setTestimonial(i, { name: e.target.value })}
                  />
                </Field>
                <BilingualField
              labels={labels}
                  label={t("testimonials.role")}
                  value={item.role}
                  onChange={(p) => setTestimonial(i, { role: p })}
                />
              </div>
            ))}
            {form.testimonials.length < MAX_TESTIMONIALS && (
              <Button
                variant="secondary"
                icon="plus"
                onClick={addTestimonial}
                style={{ alignSelf: "flex-start" }}
              >
                {t("testimonials.add")}
              </Button>
            )}
          </div>
        )}

        {tab === "layout" && (
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("layout.hint")}
            </p>
            <div className="stack" style={{ gap: 10 }}>
              {form.sectionsOrder.map((key, i) => (
                <div key={key} className="layout-row">
                  <div className="layout-row-move">
                    <Button
                      variant="ghost"
                      icon="arrowUp"
                      onClick={() => moveSection(i, -1)}
                      disabled={i === 0}
                      aria-label={t("layout.moveUp")}
                    />
                    <Button
                      variant="ghost"
                      icon="arrowDown"
                      onClick={() => moveSection(i, 1)}
                      disabled={i === form.sectionsOrder.length - 1}
                      aria-label={t("layout.moveDown")}
                    />
                  </div>
                  <div className="layout-row-name">
                    <span className="layout-row-index tnum">{i + 1}</span>
                    <span>{sectionName(key)}</span>
                  </div>
                  <Checkbox
                    checked={isSectionEnabled(key)}
                    onChange={(e) => setSectionEnabled(key, e.target.checked)}
                  >
                    <span className="row" style={{ gap: 6 }}>
                      <Icon name="eye" size={15} />
                      {t("enabled")}
                    </span>
                  </Checkbox>
                </div>
              ))}
            </div>
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

      <LivePreview
        dir={lang === "ar" ? "rtl" : "ltr"}
        heroLabel={t("tabHero")}
        heroTitle={heroTitle}
        heroSubtitle={heroSubtitle}
        previewLabel={t("preview.title")}
        previewHint={t("preview.hint")}
        disabledLabel={t("preview.disabled")}
        sections={previewSections}
      />
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Mini live preview — a lightweight schematic of the current form state.    */
/* -------------------------------------------------------------------------- */

interface LivePreviewProps {
  dir: "rtl" | "ltr";
  heroLabel: string;
  heroTitle: string;
  heroSubtitle: string;
  previewLabel: string;
  previewHint: string;
  disabledLabel: string;
  sections: PreviewSection[];
}

/**
 * A compact, schematic mirror of the public landing — Hero plus the four
 * reorderable sections in their current order, each a small card showing the
 * section name and the (localized) title/subtitle as typed. Disabled sections
 * are dimmed and struck through. Pure presentational; updates as props change.
 */
function LivePreview({
  dir,
  heroLabel,
  heroTitle,
  heroSubtitle,
  previewLabel,
  previewHint,
  disabledLabel,
  sections,
}: LivePreviewProps) {
  return (
    <aside className="le-preview" aria-label={previewLabel}>
      <div className="le-preview-head">
        <span className="le-preview-title">
          <Icon name="eye" size={15} />
          {previewLabel}
        </span>
        <span className="le-preview-hint">{previewHint}</span>
      </div>

      <div className="le-preview-canvas" dir={dir}>
        <div className="le-pv-card le-pv-hero">
          <span className="le-pv-tag">{heroLabel}</span>
          <span className="le-pv-h">{heroTitle || "—"}</span>
          {heroSubtitle && <span className="le-pv-sub">{heroSubtitle}</span>}
        </div>

        {sections.map((s) => (
          <div
            key={s.key}
            className={`le-pv-card${s.enabled ? "" : " is-off"}`}
          >
            <div className="le-pv-row">
              <span className="le-pv-tag">{s.name}</span>
              {!s.enabled && (
                <span className="le-pv-off">{disabledLabel}</span>
              )}
            </div>
            <span className="le-pv-h">{s.title || "—"}</span>
            {s.subtitle && <span className="le-pv-sub">{s.subtitle}</span>}
          </div>
        ))}
      </div>
    </aside>
  );
}

/** A leading enable/disable checkbox for a page section. */
function SectionToggle({
  label,
  checked,
  onChange,
}: {
  label: ReactNode;
  checked: boolean;
  onChange: (value: boolean) => void;
}) {
  return (
    <div className="row" style={{ gap: 8 }}>
      <Checkbox checked={checked} onChange={(e) => onChange(e.target.checked)}>
        <span className="row" style={{ gap: 6 }}>
          <Icon name="eye" size={15} />
          {label}
        </span>
      </Checkbox>
    </div>
  );
}
