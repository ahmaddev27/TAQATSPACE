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
  /** Stored media path of the admin-chosen hero image (round-tripped on save). */
  image?: string;
  /** Resolved display URL for `image`; added by the backend on read, never sent. */
  imageUrl?: string;
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
  /** Stored media path of the admin-chosen "why" image (round-tripped on save). */
  image?: string;
  /** Resolved display URL for `image`; added by the backend on read, never sent. */
  imageUrl?: string;
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

/** The reorderable public-landing section keys, in their default render order. */
export type ReorderableSectionKey =
  | "featured"
  | "why"
  | "capabilities"
  | "testimonials";

/** Default render order for the reorderable sections (used when unset). */
// Matches the original fixed JSX order so an unset `sections_order` renders the
// landing identically to before reordering was added.
export const DEFAULT_SECTIONS_ORDER: ReorderableSectionKey[] = [
  "why",
  "capabilities",
  "featured",
  "testimonials",
];

/**
 * Sanitise an arbitrary `sections_order` value into a valid ordering: keep only
 * known keys, drop duplicates, then append any missing keys so all four always
 * appear exactly once. An unset/empty input yields the default order — which is
 * why the public page renders identically to before when no order is saved.
 */
export function sanitizeSectionsOrder(
  order: string[] | undefined,
): ReorderableSectionKey[] {
  const allowed = new Set<string>(DEFAULT_SECTIONS_ORDER);
  const seen = new Set<ReorderableSectionKey>();
  const result: ReorderableSectionKey[] = [];

  for (const key of order ?? []) {
    if (allowed.has(key) && !seen.has(key as ReorderableSectionKey)) {
      const valid = key as ReorderableSectionKey;
      seen.add(valid);
      result.push(valid);
    }
  }

  for (const key of DEFAULT_SECTIONS_ORDER) {
    if (!seen.has(key)) result.push(key);
  }

  return result;
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
  /**
   * Order in which the reorderable sections render on the public page. Unknown
   * keys are ignored and missing ones are appended, so an unset value preserves
   * the default `["featured","why","capabilities","testimonials"]` order.
   */
  sections_order?: string[];
}
