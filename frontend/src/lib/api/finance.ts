import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type {
  CatalogCategoriesResult,
  CatalogItemsResult,
} from "@/lib/types/finance";

/**
 * Server-only readers for the Finance v2 catalog. Imports `serverFetch`
 * (next/headers), so Server Components only. Client components import the
 * types from `@/lib/types/finance`.
 */

/** Active catalog categories of the workspace, in display order. */
export async function ownerCatalogCategories(): Promise<CatalogCategoriesResult> {
  const res = await serverFetch<ApiEnvelope<CatalogCategoriesResult>>(
    "/workspace/finance/catalog/categories?per_page=200",
  );
  return res.data;
}

/** Active catalog items of the workspace, with their category. */
export async function ownerCatalogItems(): Promise<CatalogItemsResult> {
  const res = await serverFetch<ApiEnvelope<CatalogItemsResult>>(
    "/workspace/finance/catalog/items?per_page=200",
  );
  return res.data;
}
