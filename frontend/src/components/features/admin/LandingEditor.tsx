"use client";

import { useState, useTransition, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Tabs } from "@/components/ui/Tabs";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { updateLanding } from "@/lib/actions/admin";
import type { LandingContent, LocalizedText } from "@/lib/types";

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

interface HeroState {
  title: TextPair;
  highlight: TextPair;
  subtitle: TextPair;
  ctaPrimary: TextPair;
  ctaSecondary: TextPair;
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
}

const emptyPair = (): TextPair => ({ ar: "", en: "" });

function toPair(value?: LocalizedText): TextPair {
  return { ar: value?.ar ?? "", en: value?.en ?? "" };
}

function buildInitial(c: LandingContent): FormState {
  return {
    hero: {
      title: toPair(c.hero?.title),
      highlight: toPair(c.hero?.highlight),
      subtitle: toPair(c.hero?.subtitle),
      ctaPrimary: toPair(c.hero?.ctaPrimary),
      ctaSecondary: toPair(c.hero?.ctaSecondary),
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
  };
}

/* -------------------------------------------------------------------------- */
/*  Payload builder — prune empties so the saved JSON stays clean             */
/* -------------------------------------------------------------------------- */

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
/*  Editor                                                                    */
/* -------------------------------------------------------------------------- */

export function LandingEditor({ initial, workspaces }: LandingEditorProps) {
  const t = useTranslations("admin.landing");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [tab, setTab] = useState("hero");
  const [form, setForm] = useState<FormState>(() => buildInitial(initial));
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  // Localised labels for the Arabic/English sub-inputs, passed to each
  // module-level `BilingualField` so no component is created during render.
  const labels: BilingualLabels = { ar: t("labelAr"), en: t("labelEn") };

  const setHero = (key: keyof HeroState, pair: TextPair) =>
    setForm((f) => ({ ...f, hero: { ...f.hero, [key]: pair } }));

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
  ];

  return (
    <div className="stack" style={{ gap: 18 }}>
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
