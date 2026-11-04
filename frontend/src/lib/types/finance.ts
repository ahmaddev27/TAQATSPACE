/**
 * Client-safe types for the Finance v2 catalog (categories and items). Mirrors
 * the Laravel `CatalogCategoryResource` / `CatalogItemResource` shapes and the
 * store/update request rules.
 */
import type { SimpleMeta } from "@/lib/types";

export type CatalogKind = "sales" | "expense";

export const CATALOG_KINDS: CatalogKind[] = ["sales", "expense"];

export type CatalogUnit = "piece" | "hour" | "day" | "month" | "gb";

export const CATALOG_UNITS: CatalogUnit[] = ["piece", "hour", "day", "month", "gb"];

export interface CatalogCategory {
  id: string;
  kind: CatalogKind;
  /** Fixed at creation. */
  code: string;
  name_ar: string;
  name_en: string;
  sort_order: number;
  is_archived: boolean;
  archived_at: string | null;
  created_at: string | null;
}

export interface CatalogItem {
  id: string;
  catalog_category_id: string;
  category?: CatalogCategory;
  /** Fixed at creation. */
  code: string;
  name_ar: string;
  name_en: string;
  unit: CatalogUnit;
  /** Decimal string, e.g. "7.50". */
  price: string;
  is_archived: boolean;
  archived_at: string | null;
  created_at: string | null;
}

export interface CatalogCategoriesResult {
  categories: CatalogCategory[];
  meta: SimpleMeta;
}

export interface CatalogItemsResult {
  items: CatalogItem[];
  meta: SimpleMeta;
}

export interface CatalogCategoryCreateInput {
  kind: CatalogKind;
  code: string;
  name_ar: string;
  name_en: string;
}

export interface CatalogCategoryUpdateInput {
  name_ar: string;
  name_en: string;
}

export interface CatalogItemCreateInput {
  catalog_category_id: string;
  code: string;
  name_ar: string;
  name_en: string;
  unit: CatalogUnit;
  price: number;
}

export type CatalogItemUpdateInput = Omit<CatalogItemCreateInput, "code">;
