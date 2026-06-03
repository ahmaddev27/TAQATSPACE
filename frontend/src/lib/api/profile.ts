import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, User } from "@/lib/types";

export interface UpdateProfileInput {
  name?: string;
  phone?: string | null;
  specialty?: string | null;
  bio?: string | null;
  current_password?: string;
  new_password?: string;
  new_password_confirmation?: string;
}

/**
 * Freelancer: update own profile. Pass `FormData` when an avatar file is
 * included (multipart); otherwise a plain JSON object is fine.
 */
export async function updateProfile(
  input: UpdateProfileInput | FormData,
): Promise<User> {
  const isFormData =
    typeof FormData !== "undefined" && input instanceof FormData;

  const res = await serverFetch<ApiEnvelope<User>>("/member/profile", {
    method: "PUT",
    body: isFormData ? (input as FormData) : JSON.stringify(input),
  });
  return res.data;
}
