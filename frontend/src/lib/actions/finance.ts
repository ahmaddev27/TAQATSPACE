"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type {
  CatalogCategory,
  CatalogCategoryCreateInput,
  CatalogCategoryUpdateInput,
  CatalogItem,
  CatalogItemCreateInput,
  CatalogItemUpdateInput,
} from "@/lib/types/finance";

/**
 * Finance v2 catalog mutations. Each wraps `authedMutate` and revalidates the
 * catalog page on success. Permission and flag enforcement live on the backend
 * (`finance_v2` flag, owner or `pos_manage_products`).
 */

const BASE = "/workspace/finance/catalog";

function revalidateCatalog(): void {
  revalidatePath("/[locale]/(dashboard)/owner/finance/catalog", "page");
}

async function mutate<T>(
  path: string,
  method: "POST" | "PUT" | "DELETE",
  body?: unknown,
): Promise<ActionResult<T>> {
  const result = await authedMutate<T>(`${BASE}${path}`, { method, body });
  if (result.ok) revalidateCatalog();
  return result;
}

export async function createCatalogCategory(
  input: CatalogCategoryCreateInput,
): Promise<ActionResult<CatalogCategory>> {
  return mutate("/categories", "POST", input);
}

export async function updateCatalogCategory(
  id: string,
  input: CatalogCategoryUpdateInput,
): Promise<ActionResult<CatalogCategory>> {
  return mutate(`/categories/${id}`, "PUT", input);
}

export async function archiveCatalogCategory(
  id: string,
): Promise<ActionResult<CatalogCategory>> {
  return mutate(`/categories/${id}`, "DELETE");
}

/** Persist a new category order: `ids` is the full desired sequence. */
export async function reorderCatalogCategories(
  ids: string[],
): Promise<ActionResult<null>> {
  return mutate("/categories/reorder", "POST", { ids });
}

export async function createCatalogItem(
  input: CatalogItemCreateInput,
): Promise<ActionResult<CatalogItem>> {
  return mutate("/items", "POST", input);
}

export async function updateCatalogItem(
  id: string,
  input: CatalogItemUpdateInput,
): Promise<ActionResult<CatalogItem>> {
  return mutate(`/items/${id}`, "PUT", input);
}

export async function archiveCatalogItem(
  id: string,
): Promise<ActionResult<CatalogItem>> {
  return mutate(`/items/${id}`, "DELETE");
}
