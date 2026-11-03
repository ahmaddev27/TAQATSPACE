"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "./client";

/** Enrollment material shown once while the user scans the QR code. */
export interface TwoFactorEnrollment {
  secret: string;
  otpauth_url: string;
  qr_svg: string;
}

/** Start enrollment: the backend stores an unconfirmed secret and returns the QR. */
export async function enableTwoFactorAction(): Promise<ActionResult<TwoFactorEnrollment>> {
  return authedMutate<TwoFactorEnrollment>("/auth/two-factor/enable");
}

/** Confirm enrollment with the first code; returns the one-time recovery codes. */
export async function confirmTwoFactorAction(
  code: string,
): Promise<ActionResult<{ recovery_codes: string[] }>> {
  const result = await authedMutate<{ recovery_codes: string[] }>("/auth/two-factor/confirm", {
    body: { code },
  });
  if (result.ok) revalidatePath("/settings/security");
  return result;
}

/** Turn 2FA off; needs a valid authenticator or recovery code. */
export async function disableTwoFactorAction(input: {
  code?: string;
  recovery_code?: string;
}): Promise<ActionResult<undefined>> {
  const result = await authedMutate<undefined>("/auth/two-factor/disable", { body: input });
  if (result.ok) revalidatePath("/settings/security");
  return result;
}
