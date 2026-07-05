"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type {
  AdjustStockInput,
  PosOrder,
  PosProduct,
  PosProductInput,
} from "@/lib/types/pos";

/**
 * Owner POS mutations (catalogue, inventory, orders). Each wraps `authedMutate`
 * and revalidates the owner POS page on success. Permission enforcement lives on
 * the backend (`pos_manage_products` / `pos_sell`); owners always pass.
 */

function revalidatePos(): void {
  revalidatePath("/[locale]/(dashboard)/owner/pos", "page");
}

/* --------------------------------- Products ---------------------------------- */

export async function createPosProduct(
  input: PosProductInput,
): Promise<ActionResult<PosProduct>> {
  const result = await authedMutate<PosProduct>("/pos/products", {
    method: "POST",
    body: input,
  });
  if (result.ok) revalidatePos();
  return result;
}

export async function updatePosProduct(
  productId: string,
  input: Partial<PosProductInput>,
): Promise<ActionResult<PosProduct>> {
  const result = await authedMutate<PosProduct>(`/pos/products/${productId}`, {
    method: "PUT",
    body: input,
  });
  if (result.ok) revalidatePos();
  return result;
}

export async function deletePosProduct(
  productId: string,
): Promise<ActionResult> {
  const result = await authedMutate(`/pos/products/${productId}`, {
    method: "DELETE",
  });
  if (result.ok) revalidatePos();
  return result;
}

/** Restock or manually adjust a tracked product's inventory. */
export async function adjustPosStock(
  productId: string,
  input: AdjustStockInput,
): Promise<ActionResult<PosProduct>> {
  const result = await authedMutate<PosProduct>(
    `/pos/products/${productId}/stock`,
    { method: "POST", body: input },
  );
  if (result.ok) revalidatePos();
  return result;
}

/* ---------------------------------- Orders ----------------------------------- */

/** Void a pending order (no stock was consumed). */
export async function cancelPosOrder(
  orderId: string,
): Promise<ActionResult<PosOrder>> {
  const result = await authedMutate<PosOrder>(`/pos/orders/${orderId}/cancel`, {
    method: "POST",
  });
  if (result.ok) revalidatePos();
  return result;
}
