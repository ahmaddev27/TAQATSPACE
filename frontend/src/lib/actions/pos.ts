"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type {
  AdjustStockInput,
  CreatePosOrderInput,
  PayPosOrderInput,
  PosAdvanceStatus,
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

/**
 * Orders surface on the owner POS page, the cashier terminal, and both POS
 * reports pages (a settled/refunded order shifts the aggregates).
 */
function revalidateOrders(): void {
  revalidatePath("/[locale]/(dashboard)/owner/pos", "page");
  revalidatePath("/[locale]/(dashboard)/owner/pos/reports", "page");
  revalidatePath("/[locale]/(dashboard)/cashier", "page");
  revalidatePath("/[locale]/(dashboard)/cashier/reports", "page");
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

/** Ring up a new order (created in the `new` state, awaiting fulfillment/payment). */
export async function createPosOrder(
  input: CreatePosOrderInput,
): Promise<ActionResult<PosOrder>> {
  const result = await authedMutate<PosOrder>("/pos/orders", {
    method: "POST",
    body: input,
  });
  if (result.ok) revalidateOrders();
  return result;
}

/**
 * Settle an order with cash or a bank transfer. Payment is decoupled from the
 * fulfillment `status` — an order can be paid at any point while it is unpaid
 * and not closed. Consumes stock on the backend.
 */
export async function payPosOrder(
  orderId: string,
  input: PayPosOrderInput,
): Promise<ActionResult<PosOrder>> {
  const result = await authedMutate<PosOrder>(`/pos/orders/${orderId}/pay`, {
    method: "POST",
    body: input,
  });
  if (result.ok) revalidateOrders();
  return result;
}

/**
 * Advance an order's fulfillment status (preparing → ready → completed).
 * Notifies the order's member on the backend. Independent of payment.
 */
export async function updateOrderStatus(
  orderId: string,
  status: PosAdvanceStatus,
): Promise<ActionResult<PosOrder>> {
  const result = await authedMutate<PosOrder>(`/pos/orders/${orderId}/status`, {
    method: "POST",
    body: { status },
  });
  if (result.ok) revalidateOrders();
  return result;
}

/**
 * Refund a paid order: restores stock, records an offsetting payment, and marks
 * the order refunded. Requires `pos_refund` (owners always pass).
 */
export async function refundOrder(
  orderId: string,
): Promise<ActionResult<PosOrder>> {
  const result = await authedMutate<PosOrder>(`/pos/orders/${orderId}/refund`, {
    method: "POST",
  });
  if (result.ok) revalidateOrders();
  return result;
}

/** Void an open, unpaid order (no stock was consumed). */
export async function cancelPosOrder(
  orderId: string,
): Promise<ActionResult<PosOrder>> {
  const result = await authedMutate<PosOrder>(`/pos/orders/${orderId}/cancel`, {
    method: "POST",
  });
  if (result.ok) revalidateOrders();
  return result;
}
