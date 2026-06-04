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
/*  about — a lead paragraph plus titled sections                              */
/* -------------------------------------------------------------------------- */

export interface AboutSection {
  heading?: LocalizedText;
  body?: LocalizedText;
}

export interface AboutContent {
  lead?: LocalizedText;
  sections?: AboutSection[];
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
