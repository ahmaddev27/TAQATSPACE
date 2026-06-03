"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "./client";
import type { Subscription, User } from "@/lib/types";

/**
 * Freelancer Server Actions (M06). Client components invoke these instead of
 * touching the Laravel API directly, so the bearer cookie never leaves the
 * server. Each action returns the shared `ActionResult` shape.
 */

/** Cancel an active subscription, then refresh the dashboard + subscription views. */
export async function cancelSubscriptionAction(
  subscriptionId: string,
): Promise<ActionResult<Subscription>> {
  const result = await authedMutate<Subscription>(
    `/member/subscriptions/${subscriptionId}/cancel`,
    { method: "PUT" },
  );

  if (result.ok) {
    revalidatePath("/freelancer/subscription");
    revalidatePath("/freelancer");
  }

  return result;
}

/** Plain (no-avatar) profile fields the freelancer can edit. */
export interface ProfileFormInput {
  name: string;
  phone: string;
  specialty: string;
  bio: string;
}

/**
 * Update name/phone/specialty/bio (+ optional avatar). When an avatar file is
 * present the caller passes a `FormData`; Laravel needs a `_method=PUT` spoof
 * because multipart bodies can't be sent with a real PUT through the proxy, so
 * we POST with the spoof — `authedMutate` forwards the FormData untouched.
 */
export async function updateProfileAction(
  input: ProfileFormInput,
  avatar: File | null,
): Promise<ActionResult<User>> {
  let result: ActionResult<User>;

  if (avatar) {
    const fd = new FormData();
    fd.set("name", input.name);
    fd.set("phone", input.phone);
    fd.set("specialty", input.specialty);
    fd.set("bio", input.bio);
    fd.set("avatar", avatar);
    fd.set("_method", "PUT");
    result = await authedMutate<User>("/member/profile", {
      method: "POST",
      formData: fd,
    });
  } else {
    result = await authedMutate<User>("/member/profile", {
      method: "PUT",
      body: {
        name: input.name,
        phone: input.phone,
        specialty: input.specialty,
        bio: input.bio,
      },
    });
  }

  if (result.ok) {
    revalidatePath("/freelancer/profile");
    revalidatePath("/freelancer");
  }

  return result;
}

/** Change-password payload (separate section from the profile form). */
export interface ChangePasswordInput {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

/** Change the account password via the profile endpoint. */
export async function changePasswordAction(
  input: ChangePasswordInput,
): Promise<ActionResult<User>> {
  return authedMutate<User>("/member/profile", {
    method: "PUT",
    body: input,
  });
}
