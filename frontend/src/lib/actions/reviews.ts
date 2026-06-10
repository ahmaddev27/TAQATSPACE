"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";

export interface SubmitReviewInput {
  workspace_id: string;
  rating: number;
  comment?: string;
}

/**
 * Freelancer: submit a 1–5 star review (+ optional comment) for a workspace they
 * subscribe to. The backend enforces "subscribed" and "one review per space".
 */
export async function submitReview(
  input: SubmitReviewInput,
): Promise<ActionResult<null>> {
  const body: Record<string, unknown> = {
    workspace_id: input.workspace_id,
    rating: input.rating,
  };
  if (input.comment && input.comment.trim() !== "") {
    body.comment = input.comment.trim();
  }

  const result = await authedMutate<null>("/reviews", { method: "POST", body });
  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/freelancer/subscription", "page");
  }
  return result;
}
