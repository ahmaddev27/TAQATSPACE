import type { LocalizedText } from "@/lib/types/landing";

/**
 * Read the active-locale side of a CMS bilingual field, falling back to a
 * default when the admin hasn't set it. Keeps public pages identical to their
 * i18n defaults until content is entered in the "rm" section.
 */
export function cmsText(
  field: LocalizedText | undefined,
  locale: string,
  fallback = "",
): string {
  const value = locale === "en" ? field?.en : field?.ar;
  const trimmed = value?.trim();
  return trimmed ? trimmed : fallback;
}
