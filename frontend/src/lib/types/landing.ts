/**
 * Landing Page CMS content schema.
 *
 * Mirrors the backend contract for `GET/PUT /api/(admin/)landing`. Every field
 * is optional: the public page falls back to the existing i18n dictionary for
 * anything the Super Admin has not set. Bilingual copy is a `{ ar, en }` pair.
 */

/** A bilingual text value. Either side may be absent/empty. */
export interface LocalizedText {
  ar?: string;
  en?: string;
}

export interface LandingHero {
  title?: LocalizedText;
  highlight?: LocalizedText;
  subtitle?: LocalizedText;
  ctaPrimary?: LocalizedText;
  ctaSecondary?: LocalizedText;
}

export interface LandingStat {
  value?: string;
  label?: LocalizedText;
}

export interface FeaturedSection {
  enabled?: boolean;
  title?: LocalizedText;
  subtitle?: LocalizedText;
  workspaceIds?: string[];
}

export interface WhySection {
  enabled?: boolean;
  title?: LocalizedText;
  highlight?: LocalizedText;
  subtitle?: LocalizedText;
}

export interface CapabilitiesSection {
  enabled?: boolean;
  title?: LocalizedText;
  subtitle?: LocalizedText;
}

export interface TestimonialsSection {
  enabled?: boolean;
  title?: LocalizedText;
}

export interface LandingSections {
  featured?: FeaturedSection;
  why?: WhySection;
  capabilities?: CapabilitiesSection;
  testimonials?: TestimonialsSection;
}

export interface LandingTestimonial {
  text?: LocalizedText;
  name?: string;
  role?: LocalizedText;
}

/** Full editable landing content. All fields optional (partial saves allowed). */
export interface LandingContent {
  hero?: LandingHero;
  stats?: LandingStat[];
  sections?: LandingSections;
  testimonials?: LandingTestimonial[];
}
