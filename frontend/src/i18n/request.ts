import { getRequestConfig } from "next-intl/server";
import { hasLocale } from "next-intl";
import { routing } from "./routing";

type Messages = Record<string, unknown>;

/**
 * Namespace files merged on top of the base `messages/${locale}.json`.
 *
 * Each area owns its own file under `messages/${locale}/<area>.json` so agents
 * never edit a shared bundle. Add new namespaces here as areas are introduced.
 */
const NAMESPACES = [
  "common",
  "owner",
  "freelancer",
  "admin",
  "explore",
  "workspace",
  "invoices",
  "messaging",
  "broadcast",
  "announcements",
  "profile",
  "management",
  "chat",
] as const;

/** Recursively merge `source` into `target` (objects deep, scalars overwrite). */
function deepMerge(target: Messages, source: Messages): Messages {
  for (const [key, value] of Object.entries(source)) {
    const existing = target[key];
    if (isPlainObject(existing) && isPlainObject(value)) {
      target[key] = deepMerge({ ...existing }, value);
    } else {
      target[key] = value;
    }
  }
  return target;
}

function isPlainObject(value: unknown): value is Messages {
  return (
    typeof value === "object" &&
    value !== null &&
    !Array.isArray(value)
  );
}

/** Load a namespace file, tolerating absent ones (areas may not ship every namespace). */
async function loadNamespace(
  locale: string,
  namespace: string,
): Promise<Messages | null> {
  try {
    const mod = await import(`../messages/${locale}/${namespace}.json`);
    return (mod.default ?? mod) as Messages;
  } catch {
    return null;
  }
}

export default getRequestConfig(async ({ requestLocale }) => {
  const requested = await requestLocale;
  const locale = hasLocale(routing.locales, requested)
    ? requested
    : routing.defaultLocale;

  const base = (await import(`../messages/${locale}.json`)).default as Messages;

  const namespaceModules = await Promise.all(
    NAMESPACES.map((ns) => loadNamespace(locale, ns)),
  );

  const messages = namespaceModules.reduce<Messages>(
    (acc, mod) => (mod ? deepMerge(acc, mod) : acc),
    { ...base },
  );

  return { locale, messages };
});
