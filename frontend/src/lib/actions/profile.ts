"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "./client";
import type { User } from "@/lib/types";

/**
 * Self-service profile Server Actions, shared by every authenticated role
 * (admin / freelancer). They target the role-agnostic `/profile` endpoint —
 * identity comes from the bearer cookie, so the same actions serve any user.
 *
 * Callers pass the dashboard paths to revalidate so the freelancer and admin
 * pages each refresh their own cached RSC payloads after a successful save.
 */

/** Editable (non-avatar) profile fields. Freelancer-only fields stay empty for admins. */
export interface ProfileFormInput {
  name: string;
  phone: string;
  specialty: string;
  bio: string;
}

/**
 * Update name/phone (+ freelancer specialty/bio) and an optional avatar. When an
 * avatar file is present we send `multipart/form-data` with a `_method=PUT`
 * spoof, because multipart bodies can't be sent through the proxy as a real PUT;
 * otherwise we send a plain JSON PUT.
 */
export async function updateProfileAction(
  input: ProfileFormInput,
  avatar: File | null,
  revalidate: string[] = [],
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
    result = await authedMutate<User>("/profile", {
      method: "POST",
      formData: fd,
    });
  } else {
    result = await authedMutate<User>("/profile", {
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
    for (const path of revalidate) revalidatePath(path);
  }

  return result;
}

/** Change-password payload (separate section from the profile form). */
export interface ChangePasswordInput {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

/** Change the current user's password via the dedicated password endpoint. */
export async function changePasswordAction(
  input: ChangePasswordInput,
): Promise<ActionResult<User>> {
  return authedMutate<User>("/profile/password", {
    method: "PUT",
    body: input,
  });
}
