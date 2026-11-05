"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { ImportBatch } from "@/lib/types/financeImports";

/**
 * Finance v2 import mutations. The preview writes nothing; the commit writes
 * every row or none. Permission and flag enforcement live on the backend.
 */

const BASE = "/workspace/finance/imports";

/** Multipart: the XLSX file and its `type`. */
export async function previewImport(formData: FormData): Promise<ActionResult<ImportBatch>> {
  return authedMutate<ImportBatch>(`${BASE}/preview`, { method: "POST", formData });
}

export async function commitImport(id: string): Promise<ActionResult<ImportBatch>> {
  const result = await authedMutate<ImportBatch>(`${BASE}/${id}/commit`, { method: "POST" });
  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/owner/finance/catalog", "page");
  }
  return result;
}
