import { defineRouting } from "next-intl/routing";

export const routing = defineRouting({
  locales: ["ar", "en"],
  defaultLocale: "ar",
  // Both locales are prefixed (/ar, /en); "/" redirects to "/ar". "always" is
  // robust in the production standalone build (unprefixed default-locale paths
  // do not resolve there under "as-needed").
  localePrefix: "always",
  // On first visit, "/" follows the browser's Accept-Language (device language)
  // before falling back to the default locale. An explicit choice (the language
  // toggle) wins on later visits via the stored locale.
  localeDetection: true,
});

export type Locale = (typeof routing.locales)[number];
