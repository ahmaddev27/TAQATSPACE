/**
 * Client-safe types for Finance v2 XLSX imports. Mirrors the Laravel
 * `ImportBatchResource` shape.
 */

export type ImportType = "catalog_items" | "withdrawals";

export const IMPORT_TYPES: ImportType[] = ["catalog_items", "withdrawals"];

export type ImportStatus = "previewed" | "failed" | "committed";

export interface ImportRowError {
  row: number;
  column: string | null;
  message: string;
}

export interface ImportBatch {
  id: string;
  type: ImportType;
  status: ImportStatus;
  row_count: number;
  error_count: number;
  /** At most the first 200 errors; `error_count` is the full count. */
  errors: ImportRowError[];
  can_commit: boolean;
  committed_at: string | null;
}
