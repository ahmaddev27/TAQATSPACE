import type { Locator, Page } from "@playwright/test";

/** The input of a `<Field label=...>` (the label is not bound with htmlFor, so match the wrapper). */
export function fieldInput(scope: Page | Locator, label: string): Locator {
  return scope.locator(".field").filter({ hasText: label }).first().locator("input, textarea, select").first();
}

export function dialog(page: Page): Locator {
  return page.getByRole("dialog");
}

export function tab(page: Page, name: string): Locator {
  return page.getByRole("tab", { name, exact: true });
}

/** Every value stored under `key` anywhere in a JSON document. */
export function collect(value: unknown, key: string, found: unknown[] = []): unknown[] {
  if (value === null || typeof value !== "object") return found;
  for (const [name, child] of Object.entries(value as Record<string, unknown>)) {
    if (name === key) found.push(child);
    collect(child, key, found);
  }
  return found;
}

/** The first array found in a JSON document (the list a paginated endpoint returns). */
export function firstArray(value: unknown): unknown[] {
  if (Array.isArray(value)) return value;
  if (value === null || typeof value !== "object") return [];
  for (const child of Object.values(value as Record<string, unknown>)) {
    const found = firstArray(child);
    if (found.length > 0) return found;
  }
  return [];
}
