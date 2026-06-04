"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Tabs } from "@/components/ui/Tabs";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { updateContent } from "@/lib/actions/admin";
import type {
  AboutContent,
  ContentKey,
  FaqContent,
  HowItWorksContent,
  LocalizedText,
  SiteContent,
} from "@/lib/types";

const MAX_FAQ = 30;
const MAX_SECTIONS = 20;
const MAX_STEPS = 12;

export interface ContentManagerProps {
  site: SiteContent;
  faq: FaqContent;
  about: AboutContent;
  howItWorks: HowItWorksContent;
}

/* -------------------------------------------------------------------------- */
/*  Local form state                                                          */
/*                                                                            */
/*  As in LandingEditor, every optional `{ar,en}` is normalised to a concrete */
/*  `{ar:"",en:""}` pair so controlled inputs never flip controlled state.    */
/*  Payloads are pruned on save (empty pairs dropped) to keep the JSON clean. */
/* -------------------------------------------------------------------------- */

interface TextPair {
  ar: string;
  en: string;
}

interface SiteState {
  contactEmail: string;
  contactPhone: string;
  whatsapp: string;
  address: TextPair;
  facebook: string;
  instagram: string;
  twitter: string;
  linkedin: string;
}

interface FaqItemState {
  question: TextPair;
  answer: TextPair;
}

interface AboutSectionState {
  heading: TextPair;
  body: TextPair;
}

interface StepState {
  title: TextPair;
  description: TextPair;
}

const emptyPair = (): TextPair => ({ ar: "", en: "" });

function toPair(value?: LocalizedText): TextPair {
  return { ar: value?.ar ?? "", en: value?.en ?? "" };
}

function buildSite(c: SiteContent): SiteState {
  return {
    contactEmail: c.contact_email ?? "",
    contactPhone: c.contact_phone ?? "",
    whatsapp: c.whatsapp ?? "",
    address: toPair(c.address),
    facebook: c.social?.facebook ?? "",
    instagram: c.social?.instagram ?? "",
    twitter: c.social?.twitter ?? "",
    linkedin: c.social?.linkedin ?? "",
  };
}

function buildFaq(c: FaqContent): FaqItemState[] {
  return (c.items ?? []).slice(0, MAX_FAQ).map((item) => ({
    question: toPair(item.question),
    answer: toPair(item.answer),
  }));
}

function buildAboutLead(c: AboutContent): TextPair {
  return toPair(c.lead);
}

function buildAboutSections(c: AboutContent): AboutSectionState[] {
  return (c.sections ?? []).slice(0, MAX_SECTIONS).map((s) => ({
    heading: toPair(s.heading),
    body: toPair(s.body),
  }));
}

function buildSteps(c: HowItWorksContent): StepState[] {
  return (c.steps ?? []).slice(0, MAX_STEPS).map((s) => ({
    title: toPair(s.title),
    description: toPair(s.description),
  }));
}

/* -------------------------------------------------------------------------- */
/*  Payload builders — prune empties so the saved JSON stays clean            */
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

/** A trimmed string, or `undefined` when empty. */
function clean(value: string): string | undefined {
  const trimmed = value.trim();
  return trimmed ? trimmed : undefined;
}

/** Drop keys whose value is `undefined`; return `undefined` if nothing is left. */
function compact<T extends Record<string, unknown>>(obj: T): T | undefined {
  const entries = Object.entries(obj).filter(([, v]) => v !== undefined);
  return entries.length ? (Object.fromEntries(entries) as T) : undefined;
}

function buildSitePayload(form: SiteState): SiteContent {
  const social = compact({
    facebook: clean(form.facebook),
    instagram: clean(form.instagram),
    twitter: clean(form.twitter),
    linkedin: clean(form.linkedin),
  });

  return compact({
    contact_email: clean(form.contactEmail),
    contact_phone: clean(form.contactPhone),
    whatsapp: clean(form.whatsapp),
    address: pairToText(form.address),
    social,
  }) ?? {};
}

function buildFaqPayload(items: FaqItemState[]): FaqContent {
  const out = items
    .map((item) => {
      const question = pairToText(item.question);
      const answer = pairToText(item.answer);
      if (!question && !answer) return undefined;
      return compact({ question, answer });
    })
    .filter((item): item is NonNullable<typeof item> => item !== undefined);

  return out.length ? { items: out } : {};
}

function buildAboutPayload(
  lead: TextPair,
  sections: AboutSectionState[],
): AboutContent {
  const leadText = pairToText(lead);
  const out = sections
    .map((s) => {
      const heading = pairToText(s.heading);
      const body = pairToText(s.body);
      if (!heading && !body) return undefined;
      return compact({ heading, body });
    })
    .filter((s): s is NonNullable<typeof s> => s !== undefined);

  return compact({
    lead: leadText,
    sections: out.length ? out : undefined,
  }) ?? {};
}

function buildStepsPayload(steps: StepState[]): HowItWorksContent {
  const out = steps
    .map((s) => {
      const title = pairToText(s.title);
      const description = pairToText(s.description);
      if (!title && !description) return undefined;
      return compact({ title, description });
    })
    .filter((s): s is NonNullable<typeof s> => s !== undefined);

  return out.length ? { steps: out } : {};
}

/* -------------------------------------------------------------------------- */
/*  Reusable bilingual field (module-level so it is never re-created)         */
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

/** Shared error block, surfacing 422 field errors after a failed save. */
function ErrorList({ errors }: { errors: Record<string, string[]> }) {
  if (Object.keys(errors).length === 0) return null;
  return (
    <div className="field is-error stack" style={{ gap: 4 }}>
      {Object.values(errors)
        .flat()
        .map((message) => (
          <span key={message} className="hint">
            {message}
          </span>
        ))}
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Content manager                                                           */
/* -------------------------------------------------------------------------- */

export function ContentManager({
  site,
  faq,
  about,
  howItWorks,
}: ContentManagerProps) {
  const t = useTranslations("admin.rm");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [savingKey, setSavingKey] = useState<ContentKey | null>(null);

  const [tab, setTab] = useState("site");

  const [siteForm, setSiteForm] = useState<SiteState>(() => buildSite(site));
  const [faqItems, setFaqItems] = useState<FaqItemState[]>(() => buildFaq(faq));
  const [aboutLead, setAboutLead] = useState<TextPair>(() =>
    buildAboutLead(about),
  );
  const [aboutSections, setAboutSections] = useState<AboutSectionState[]>(() =>
    buildAboutSections(about),
  );
  const [steps, setSteps] = useState<StepState[]>(() => buildSteps(howItWorks));

  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const labels: BilingualLabels = { ar: t("labelAr"), en: t("labelEn") };

  /** Run a save for one key, surfacing toast + 422 errors like LandingEditor. */
  function save<K extends ContentKey>(
    key: K,
    payload: Parameters<typeof updateContent<K>>[1],
  ) {
    setSavingKey(key);
    startTransition(async () => {
      const res = await updateContent(key, payload);
      setSavingKey(null);
      if (res.ok) {
        setErrors({});
        toast({ tone: "ok", title: t("saved") });
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("saveFailed"), body: res.message });
      }
    });
  }

  /* ----- site helpers --------------------------------------------------- */
  const setSiteField = (key: keyof SiteState, value: string) =>
    setSiteForm((f) => ({ ...f, [key]: value }));

  /* ----- faq helpers ---------------------------------------------------- */
  const setFaqItem = (index: number, patch: Partial<FaqItemState>) =>
    setFaqItems((items) =>
      items.map((it, i) => (i === index ? { ...it, ...patch } : it)),
    );
  const addFaqItem = () =>
    setFaqItems((items) =>
      items.length >= MAX_FAQ
        ? items
        : [...items, { question: emptyPair(), answer: emptyPair() }],
    );
  const removeFaqItem = (index: number) =>
    setFaqItems((items) => items.filter((_, i) => i !== index));

  /* ----- about helpers -------------------------------------------------- */
  const setAboutSection = (index: number, patch: Partial<AboutSectionState>) =>
    setAboutSections((s) =>
      s.map((sec, i) => (i === index ? { ...sec, ...patch } : sec)),
    );
  const addAboutSection = () =>
    setAboutSections((s) =>
      s.length >= MAX_SECTIONS
        ? s
        : [...s, { heading: emptyPair(), body: emptyPair() }],
    );
  const removeAboutSection = (index: number) =>
    setAboutSections((s) => s.filter((_, i) => i !== index));

  /* ----- how-it-works helpers ------------------------------------------- */
  const setStep = (index: number, patch: Partial<StepState>) =>
    setSteps((s) => s.map((st, i) => (i === index ? { ...st, ...patch } : st)));
  const addStep = () =>
    setSteps((s) =>
      s.length >= MAX_STEPS
        ? s
        : [...s, { title: emptyPair(), description: emptyPair() }],
    );
  const removeStep = (index: number) =>
    setSteps((s) => s.filter((_, i) => i !== index));

  const tabs = [
    { id: "site", label: t("tabSite") },
    { id: "faq", label: t("tabFaq") },
    { id: "about", label: t("tabAbout") },
    { id: "how", label: t("tabHow") },
    { id: "landing", label: t("tabLanding") },
  ];

  const isSaving = (key: ContentKey) => pending && savingKey === key;

  return (
    <div className="stack" style={{ gap: 18 }}>
      <Tabs items={tabs} value={tab} onChange={setTab} />

      {/* SITE INFO ---------------------------------------------------------- */}
      {tab === "site" && (
        <div className="card card-pad stack" style={{ gap: 18 }}>
          <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("site.hint")}
          </p>
          <div className="grid2">
            <Field label={t("site.email")}>
              <Input
                type="email"
                className="ltr"
                value={siteForm.contactEmail}
                onChange={(e) => setSiteField("contactEmail", e.target.value)}
              />
            </Field>
            <Field label={t("site.phone")}>
              <Input
                className="ltr"
                value={siteForm.contactPhone}
                onChange={(e) => setSiteField("contactPhone", e.target.value)}
              />
            </Field>
          </div>
          <Field label={t("site.whatsapp")}>
            <Input
              className="ltr"
              value={siteForm.whatsapp}
              onChange={(e) => setSiteField("whatsapp", e.target.value)}
            />
          </Field>
          <BilingualField
            labels={labels}
            label={t("site.address")}
            value={siteForm.address}
            onChange={(p) => setSiteForm((f) => ({ ...f, address: p }))}
          />

          <div className="stack" style={{ gap: 8 }}>
            <span className="label">{t("site.social")}</span>
            <div className="grid2">
              <Field label={t("site.facebook")}>
                <Input
                  className="ltr"
                  placeholder="https://"
                  value={siteForm.facebook}
                  onChange={(e) => setSiteField("facebook", e.target.value)}
                />
              </Field>
              <Field label={t("site.instagram")}>
                <Input
                  className="ltr"
                  placeholder="https://"
                  value={siteForm.instagram}
                  onChange={(e) => setSiteField("instagram", e.target.value)}
                />
              </Field>
              <Field label={t("site.twitter")}>
                <Input
                  className="ltr"
                  placeholder="https://"
                  value={siteForm.twitter}
                  onChange={(e) => setSiteField("twitter", e.target.value)}
                />
              </Field>
              <Field label={t("site.linkedin")}>
                <Input
                  className="ltr"
                  placeholder="https://"
                  value={siteForm.linkedin}
                  onChange={(e) => setSiteField("linkedin", e.target.value)}
                />
              </Field>
            </div>
          </div>

          <ErrorList errors={errors} />
          <Button
            variant="primary"
            icon="check"
            loading={isSaving("site")}
            onClick={() => save("site", buildSitePayload(siteForm))}
            style={{ alignSelf: "flex-start" }}
          >
            {isSaving("site") ? t("saving") : t("save")}
          </Button>
        </div>
      )}

      {/* FAQ ---------------------------------------------------------------- */}
      {tab === "faq" && (
        <div className="card card-pad stack" style={{ gap: 16 }}>
          <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("faq.hint")}
          </p>
          {faqItems.length === 0 && (
            <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
              {t("faq.empty")}
            </p>
          )}
          {faqItems.map((item, i) => (
            <div key={i} className="card card-pad stack" style={{ gap: 12 }}>
              <div className="between">
                <span className="label">#{i + 1}</span>
                <Button
                  variant="ghost"
                  icon="trash"
                  onClick={() => removeFaqItem(i)}
                  aria-label={t("remove")}
                />
              </div>
              <BilingualField
                labels={labels}
                label={t("faq.question")}
                value={item.question}
                onChange={(p) => setFaqItem(i, { question: p })}
              />
              <BilingualField
                labels={labels}
                label={t("faq.answer")}
                value={item.answer}
                onChange={(p) => setFaqItem(i, { answer: p })}
                multiline
              />
            </div>
          ))}
          {faqItems.length < MAX_FAQ && (
            <Button
              variant="secondary"
              icon="plus"
              onClick={addFaqItem}
              style={{ alignSelf: "flex-start" }}
            >
              {t("faq.add")}
            </Button>
          )}

          <ErrorList errors={errors} />
          <Button
            variant="primary"
            icon="check"
            loading={isSaving("faq")}
            onClick={() => save("faq", buildFaqPayload(faqItems))}
            style={{ alignSelf: "flex-start" }}
          >
            {isSaving("faq") ? t("saving") : t("save")}
          </Button>
        </div>
      )}

      {/* ABOUT -------------------------------------------------------------- */}
      {tab === "about" && (
        <div className="card card-pad stack" style={{ gap: 16 }}>
          <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("about.hint")}
          </p>
          <BilingualField
            labels={labels}
            label={t("about.lead")}
            value={aboutLead}
            onChange={setAboutLead}
            multiline
          />
          <span className="label">{t("about.sections")}</span>
          {aboutSections.length === 0 && (
            <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
              {t("about.empty")}
            </p>
          )}
          {aboutSections.map((sec, i) => (
            <div key={i} className="card card-pad stack" style={{ gap: 12 }}>
              <div className="between">
                <span className="label">#{i + 1}</span>
                <Button
                  variant="ghost"
                  icon="trash"
                  onClick={() => removeAboutSection(i)}
                  aria-label={t("remove")}
                />
              </div>
              <BilingualField
                labels={labels}
                label={t("about.heading")}
                value={sec.heading}
                onChange={(p) => setAboutSection(i, { heading: p })}
              />
              <BilingualField
                labels={labels}
                label={t("about.body")}
                value={sec.body}
                onChange={(p) => setAboutSection(i, { body: p })}
                multiline
              />
            </div>
          ))}
          {aboutSections.length < MAX_SECTIONS && (
            <Button
              variant="secondary"
              icon="plus"
              onClick={addAboutSection}
              style={{ alignSelf: "flex-start" }}
            >
              {t("about.add")}
            </Button>
          )}

          <ErrorList errors={errors} />
          <Button
            variant="primary"
            icon="check"
            loading={isSaving("about")}
            onClick={() =>
              save("about", buildAboutPayload(aboutLead, aboutSections))
            }
            style={{ alignSelf: "flex-start" }}
          >
            {isSaving("about") ? t("saving") : t("save")}
          </Button>
        </div>
      )}

      {/* HOW IT WORKS ------------------------------------------------------- */}
      {tab === "how" && (
        <div className="card card-pad stack" style={{ gap: 16 }}>
          <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("how.hint")}
          </p>
          {steps.length === 0 && (
            <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
              {t("how.empty")}
            </p>
          )}
          {steps.map((step, i) => (
            <div key={i} className="card card-pad stack" style={{ gap: 12 }}>
              <div className="between">
                <span className="label">#{i + 1}</span>
                <Button
                  variant="ghost"
                  icon="trash"
                  onClick={() => removeStep(i)}
                  aria-label={t("remove")}
                />
              </div>
              <BilingualField
                labels={labels}
                label={t("how.title")}
                value={step.title}
                onChange={(p) => setStep(i, { title: p })}
              />
              <BilingualField
                labels={labels}
                label={t("how.description")}
                value={step.description}
                onChange={(p) => setStep(i, { description: p })}
                multiline
              />
            </div>
          ))}
          {steps.length < MAX_STEPS && (
            <Button
              variant="secondary"
              icon="plus"
              onClick={addStep}
              style={{ alignSelf: "flex-start" }}
            >
              {t("how.add")}
            </Button>
          )}

          <ErrorList errors={errors} />
          <Button
            variant="primary"
            icon="check"
            loading={isSaving("how_it_works")}
            onClick={() => save("how_it_works", buildStepsPayload(steps))}
            style={{ alignSelf: "flex-start" }}
          >
            {isSaving("how_it_works") ? t("saving") : t("save")}
          </Button>
        </div>
      )}

      {/* LANDING (link out to the existing editor) -------------------------- */}
      {tab === "landing" && (
        <div className="card card-pad stack" style={{ gap: 14 }}>
          <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("landing.hint")}
          </p>
          <Link href="/admin/landing">
            <Button variant="secondary" icon="grid" iconEnd="arrowR">
              {t("landing.open")}
            </Button>
          </Link>
        </div>
      )}
    </div>
  );
}
