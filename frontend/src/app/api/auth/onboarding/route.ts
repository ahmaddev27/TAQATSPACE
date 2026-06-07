import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { serverFetch, ApiError } from "@/lib/api";
import { ROLE_COOKIE, TOKEN_MAX_AGE } from "@/lib/auth";
import type { ApiEnvelope, User, UserRole } from "@/lib/types/auth";

const isProd = process.env.NODE_ENV === "production";

interface OnboardingPayload {
  user: User;
  role: UserRole;
  /** "dashboard" (freelancer, active) | "pending" (owner, awaiting approval). */
  next: "dashboard" | "pending";
}

/**
 * Completes SSO onboarding: forwards the chosen role + role-specific data to the
 * backend (authenticated via the httpOnly bearer cookie), then refreshes the
 * non-httpOnly role cookie so middleware routes by the newly-chosen role.
 */
export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ message: "Invalid request body." }, { status: 400 });
  }

  try {
    const result = await serverFetch<ApiEnvelope<OnboardingPayload>>(
      "/auth/complete-onboarding",
      {
        method: "POST",
        body: JSON.stringify(body),
      },
    );

    const { user, role, next } = result.data;

    const store = await cookies();
    store.set(ROLE_COOKIE, role, {
      httpOnly: false,
      sameSite: "lax",
      secure: isProd,
      path: "/",
      maxAge: TOKEN_MAX_AGE,
    });

    return NextResponse.json({ user, role, next });
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
