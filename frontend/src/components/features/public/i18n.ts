import arMessages from "@/messages/ar/public.json";
import enMessages from "@/messages/en/public.json";

/**
 * Self-contained dictionary for the public marketing area.
 *
 * The shared `i18n/request.ts` only merges a fixed namespace list and does not
 * include `public`, so this area loads its own `messages/${locale}/public.json`
 * directly. Both server components (via {@link getPublicDict}) and client
 * components (via {@link PublicIntlProvider}) read from the same source so the
 * strings stay in one place and switch with the active locale.
 */
export type PublicDict = (typeof arMessages)["public"];

const DICTS: Record<string, PublicDict> = {
  ar: arMessages.public,
  en: enMessages.public,
};

export type PublicLocale = "ar" | "en";

export function normalizeLocale(locale: string): PublicLocale {
  return locale === "en" ? "en" : "ar";
}

/** Resolve the public dictionary for a locale (server + client safe). */
export function getPublicDict(locale: string): PublicDict {
  return DICTS[normalizeLocale(locale)];
}

/** Interpolate `{name}` placeholders in a template string. */
export function interpolate(
  template: string,
  values: Record<string, string | number>,
): string {
  return template.replace(/\{(\w+)\}/g, (_, key: string) =>
    key in values ? String(values[key]) : `{${key}}`,
  );
}
