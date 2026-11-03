"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type {
  Plan,
  PlanCreateInput,
  PlanInput,
  PlanRemoveResult,
} from "@/lib/types/financePlans";

/**
 * Finance v2 plan mutations. Each wraps `authedMutate` and revalidates the
 * plans page on success. Permission and flag enforcement live on the backend
 * (`finance_v2` flag, owner only).
 */

const BASE = "/workspace/finance/plans";

async function mutate<T>(
  path: string,
  method: "POST" | "PUT" | "DELETE",
  body?: unknown,
): Promise<ActionResult<T>> {
  const result = await authedMutate<T>(`${BASE}${path}`, { method, body });
  if (result.ok) revalidatePath("/[locale]/(dashboard)/owner/finance/plans", "page");
  return result;
}

export async function createPlan(input: PlanCreateInput): Promise<ActionResult<Plan>> {
  return mutate("", "POST", input);
}

export async function updatePlan(id: string, input: PlanInput): Promise<ActionResult<Plan>> {
  return mutate(`/${id}`, "PUT", input);
}

export async function archivePlan(id: string): Promise<ActionResult<Plan>> {
  return mutate(`/${id}/archive`, "POST");
}

/** Deletes an unused plan; the backend archives one that is in use instead. */
export async function removePlan(id: string): Promise<ActionResult<PlanRemoveResult>> {
  return mutate(`/${id}`, "DELETE");
}
