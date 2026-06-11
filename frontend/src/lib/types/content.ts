/**
 * Site-wide CMS content schemas (the "rm" content-management section).
 *
 * Mirrors the backend contract for `GET /content/{key}` (public) and
 * `GET/PUT /admin/content/{key}` (admin), where `key` is one of
 * `site | faq | about | how_it_works`. Every field is optional: the public
 * pages fall back to the existing i18n dictionary for anything the Super Admin
 * has not set. Bilingual copy is a `{ ar, en }` pair (see {@link LocalizedText}).
 */

import type { LocalizedText } from "./landing";

/** The four content blobs the Super Admin can edit, keyed by their API slug. */
export type ContentKey = "site" | "faq" | "about" | "how_it_works";

/* -------------------------------------------------------------------------- */
/*  branding — site name, SEO meta, favicon + light/dark logos                 */
/* -------------------------------------------------------------------------- */

/**
 * Admin-controlled site branding (`GET /branding` public, `GET/PUT
 * /admin/branding` admin). Image fields are canonical media-disk PATHS; the
 * backend resolves each to a sibling `*Url` for display. Everything is
 * optional — the app falls back to its built-in branding for anything unset.
 */
export interface Branding {
  site_name?: string;
  meta_title?: string;
  meta_description?: string;
  /** Canonical storage paths (round-tripped on save). */
  favicon?: string;
  logo_light?: string;
  logo_dark?: string;
  /** Resolved display URLs (read-only, added by the backend). */
  faviconUrl?: string;
  logo_lightUrl?: string;
  logo_darkUrl?: string;
}

/* -------------------------------------------------------------------------- */
/*  site — contact / social details surfaced on the contact page + footer      */
/* -------------------------------------------------------------------------- */

export interface SiteSocial {
  facebook?: string;
  instagram?: string;
  twitter?: string;
  linkedin?: string;
}

export interface SiteContent {
  contact_email?: string;
  contact_phone?: string;
  whatsapp?: string;
  address?: LocalizedText;
  social?: SiteSocial;
}

/* -------------------------------------------------------------------------- */
/*  faq — a flat list of question/answer pairs                                 */
/* -------------------------------------------------------------------------- */

export interface FaqItem {
  question?: LocalizedText;
  answer?: LocalizedText;
}

export interface FaqContent {
  items?: FaqItem[];
}

/* -------------------------------------------------------------------------- */
/*  about — hero, stats, mission/vision, values, sections, CTA                */
/* -------------------------------------------------------------------------- */

export interface AboutStat {
  value?: string;            // locale-neutral number, e.g. "+128"
  label?: LocalizedText;
}

export interface AboutValue {
  title?: LocalizedText;
  body?: LocalizedText;
}

export interface AboutSection {
  heading?: LocalizedText;
  body?: LocalizedText;
  image?: string;            // stored path
  imageUrl?: string;         // resolved on read (read-only)
}

export interface AboutContent {
  eyebrow?: LocalizedText;
  title1?: LocalizedText;
  title2?: LocalizedText;    // the highlighted span of the title
  lead?: LocalizedText;
  stats?: AboutStat[];
  mission?: { title?: LocalizedText; body?: LocalizedText };
  vision?: { title?: LocalizedText; body?: LocalizedText };
  valuesTitle?: LocalizedText;
  values?: AboutValue[];
  sections?: AboutSection[];
  cta?: { title?: LocalizedText; body?: LocalizedText };
  image?: string;            // hero collage main image (stored path)
  imageUrl?: string;         // resolved (read-only)
  imageSecondary?: string;   // hero collage secondary image (stored path)
  imageSecondaryUrl?: string;
}

/* -------------------------------------------------------------------------- */
/*  how_it_works — an ordered list of steps                                    */
/* -------------------------------------------------------------------------- */

export interface HowItWorksStep {
  title?: LocalizedText;
  description?: LocalizedText;
}

export interface HowItWorksContent {
  steps?: HowItWorksStep[];
}
