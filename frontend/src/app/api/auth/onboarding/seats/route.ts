import { NextResponse } from "next/server";
import { serverFetch, ApiError } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types/auth";
import type { Seat } from "@/lib/types";

/**
 * Forwards the onboarding seat-setup payload to the backend (authenticated via
 * the httpOnly bearer cookie) so a still-pending owner can generate their
 * workspace seats during onboarding, then returns the created seats.
 */
export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ message: "Invalid request body." }, { status: 400 });
  }

  try {
    const result = await serverFetch<ApiEnvelope<{ seats: Seat[] }>>(
      "/auth/onboarding/seats",
      {
        method: "POST",
        body: JSON.stringify(body),
      },
    );

    return NextResponse.json({ seats: result.data.seats });
  } catch (err) {
    if (err instanceof ApiError) {
      return NextResponse.json(
        { message: err.message, errors: err.body?.errors },
        { status: err.status },
      );
    }
    return NextResponse.json(
      { message: "Unable to reach the onboarding server." },
      { status: 502 },
    );
  }
}
