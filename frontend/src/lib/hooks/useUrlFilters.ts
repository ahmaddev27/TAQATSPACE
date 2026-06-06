"use client";

import { useCallback, useMemo } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";

/**
 * Sync a fixed set of list filters with the URL query string.
 *
 * Filter state lives in the URL (not component state) for two reasons:
 *  - the filtered view is shareable / bookmarkable and survives a refresh, and
 *  - the CSV export link can read the SAME filters straight off the URL, so the
 *    download honours exactly what the table is showing.
 *
 * Only the whitelisted `keys` are read and written; any other query params
 * already on the URL are preserved untouched. Writes use `router.replace` with
 * `scroll: false` so changing a filter doesn't push history or jump the page.
 *
 * @param keys  the filter params this list owns (e.g. ["status", "search"])
 */
export function useUrlFilters<K extends string>(keys: readonly K[]) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  // The serialised query string is a stable dependency for the memo below.
  const search = searchParams.toString();

  /** Current value of each owned key ("" when absent). */
  const filters = useMemo(() => {
    const current = new URLSearchParams(search);
    return keys.reduce<Record<K, string>>(
      (acc, key) => {
        acc[key] = current.get(key) ?? "";
        return acc;
      },
      {} as Record<K, string>,
    );
  }, [search, keys]);

  /**
   * Set (or, when empty/undefined, clear) one filter and replace the URL.
   * Always resets `page` so the user lands on the first page of the new view.
   */
  const setFilter = useCallback(
    (key: K, value: string | null | undefined) => {
      const next = new URLSearchParams(search);

      if (value == null || value === "") {
        next.delete(key);
      } else {
        next.set(key, value);
      }

      next.delete("page");

      const qs = next.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
    },
    [router, pathname, search],
  );

  /** Clear every owned filter at once (other params untouched). */
  const clearFilters = useCallback(() => {
    const next = new URLSearchParams(search);
    for (const key of keys) next.delete(key);
    next.delete("page");

    const qs = next.toString();
    router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
  }, [router, pathname, search, keys]);

  return { filters, setFilter, clearFilters };
}
