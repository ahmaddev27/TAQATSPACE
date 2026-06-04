import { defineRouting } from "next-intl/routing";

export const routing = defineRouting({
  locales: ["ar", "en"],
  defaultLocale: "ar",
  // Both locales are prefixed (/ar, /en); "/" redirects to "/ar". "always" is
  // robust in the production standalone build (unprefixed default-locale paths
  // do not resolve there under "as-needed").
  localePrefix: "always",
});

export type Locale = (typeof routing.locales)[number];
