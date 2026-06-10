"use server";

import { cookies } from "next/headers";
import { TOKEN_COOKIE } from "@/lib/auth";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type { BookingRequest, SeatType } from "@/lib/types";

export interface BookingActionInput {
  workspace_id: string;
  preferred_seat_type?: SeatType | null;
  message?: string | null;
}

/**
 * Submit a booking request for the authenticated freelancer.
 *
 * Returns `{ ok: false, status: 401 }` when no session cookie is present so the
 * client can redirect to `/login?redirect=...` without leaking the token.
 */
export async function submitBookingAction(
  input: BookingActionInput,
): Promise<ActionResult<BookingRequest>> {
  const store = await cookies();
  if (!store.get(TOKEN_COOKIE)?.value) {
    return { ok: false, status: 401, message: "unauthenticated" };
  }

  return authedMutate<BookingRequest>("/booking-requests", {
    method: "POST",
    body: {
      workspace_id: input.workspace_id,
      preferred_seat_type: input.preferred_seat_type ?? null,
      message: input.message?.trim() || null,
    },
  });
}

export interface ContactActionInput {
  name: string;
  email: string;
  subject: string;
  message: string;
}

/**
 * Submit the public "Contact us" form. Validates locally, then persists the
 * message via the public `POST /contact` endpoint (which notifies the admins).
 */
export async function submitContactAction(
  input: ContactActionInput,
): Promise<ActionResult<null>> {
  const errors: Record<string, string[]> = {};
  if (!input.name.trim()) errors.name = ["required"];
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(input.email.trim())) errors.email = ["email"];
  if (!input.subject.trim()) errors.subject = ["required"];
  if (!input.message.trim()) errors.message = ["required"];

  if (Object.keys(errors).length > 0) {
    return { ok: false, status: 422, message: "validation", errors };
  }

  return authedMutate<null>("/contact", {
    method: "POST",
    body: {
      name: input.name.trim(),
      email: input.email.trim(),
      subject: input.subject.trim(),
      message: input.message.trim(),
    },
  });
}
