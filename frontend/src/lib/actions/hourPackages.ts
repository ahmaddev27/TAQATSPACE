"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { HourPackage, HourPurchase } from "@/lib/api/hourPackages";

export interface HourPackageInput {
  code: string;
  name_ar: string;
  name_en: string;
  hours: number;
  price: number;
  currency: string;
  validity_days: number | null;
  is_active: boolean;
}

export interface ActivatePurchaseInput {
  payment_method: "cash" | "transfer";
  paid_at: string;
  receipt: File;
}

function revalidateHourPackages(): void {
  revalidatePath("/[locale]/(dashboard)/admin/hour-packages", "page");
  revalidatePath("/[locale]/(dashboard)/admin/hour-packages/purchases", "page");
}

async function done<T>(result: ActionResult<T>): Promise<ActionResult<T>> {
  if (result.ok) revalidateHourPackages();
  return result;
}

export async function createHourPackage(
  input: HourPackageInput,
): Promise<ActionResult<HourPackage>> {
  return done(
    await authedMutate<HourPackage>("/admin/hour-packages", { body: input }),
  );
}

export async function updateHourPackage(
  id: string,
  input: HourPackageInput,
): Promise<ActionResult<HourPackage>> {
  return done(
    await authedMutate<HourPackage>(`/admin/hour-packages/${id}`, {
      method: "PUT",
      body: input,
    }),
  );
}

export async function archiveHourPackage(
  id: string,
): Promise<ActionResult<HourPackage>> {
  return done(
    await authedMutate<HourPackage>(`/admin/hour-packages/${id}`, {
      method: "DELETE",
    }),
  );
}

/** Record the offline payment and receipt, crediting the frozen hours once. */
export async function activateHourPurchase(
  id: string,
  input: ActivatePurchaseInput,
): Promise<ActionResult<HourPurchase>> {
  const formData = new FormData();
  formData.append("payment_method", input.payment_method);
  formData.append("paid_at", input.paid_at);
  formData.append("receipt", input.receipt);
  return done(
    await authedMutate<HourPurchase>(`/admin/hour-purchases/${id}/activate`, {
      formData,
    }),
  );
}

export async function cancelHourPurchase(
  id: string,
): Promise<ActionResult<HourPurchase>> {
  return done(
    await authedMutate<HourPurchase>(`/admin/hour-purchases/${id}/cancel`),
  );
}

/** Manual balance correction; the reason is mandatory. */
export async function adjustHourBalance(input: {
  user_id: string;
  minutes: number;
  reason: string;
}): Promise<ActionResult<{ user_id: string; balance_minutes: number }>> {
  return done(
    await authedMutate<{ user_id: string; balance_minutes: number }>(
      "/admin/hour-adjustments",
      { body: input },
    ),
  );
}
