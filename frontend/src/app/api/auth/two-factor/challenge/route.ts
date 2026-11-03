import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { serverFetch, ApiError } from "@/lib/api";
import {
  PENDING_TWO_FACTOR_COOKIE,
  ROLE_COOKIE,
  TOKEN_COOKIE,
  TOKEN_MAX_AGE,
} from "@/lib/auth";
import type { ApiEnvelope } from "@/lib/types/auth";
import type { User, UserRole } from "@/lib/types/auth";

const isProd = process.env.NODE_ENV === "production";

/**
 * Completes the post-login 2FA challenge. The challenge-only token lives in an
 * httpOnly cookie; on success the backend upgrades that same token to a full
 * session, so we promote it to the real session cookies. The token is never
 * returned to the client.
 */
export async function POST(request: Request) {
  let body: { code?: string; recovery_code?: string };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ message: "Invalid request body." }, { status: 400 });
  }

  const store = await cookies();
  const pendingToken = store.get(PENDING_TWO_FACTOR_COOKIE)?.value;
  if (!pendingToken) {
    return NextResponse.json({ message: "No pending sign-in." }, { status: 401 });
  }

  try {
    const result = await serverFetch<ApiEnvelope<{ user: User; role: UserRole }>>(
      "/auth/two-factor/challenge",
      {
        method: "POST",
        auth: false,
        headers: { Authorization: `Bearer ${pendingToken}` },
        body: JSON.stringify({ code: body.code, recovery_code: body.recovery_code }),
      },
    );

    const { user, role } = result.data;

    store.set(TOKEN_COOKIE, pendingToken, {
      httpOnly: true,
      sameSite: "lax",
      secure: isProd,
      path: "/",
      maxAge: TOKEN_MAX_AGE,
    });
    store.set(ROLE_COOKIE, role, {
      httpOnly: false,
      sameSite: "lax",
      secure: isProd,
      path: "/",
      maxAge: TOKEN_MAX_AGE,
    });
    store.delete(PENDING_TWO_FACTOR_COOKIE);

    return NextResponse.json({ user, role });
  } catch (err) {
    if (err instanceof ApiError) {
      return NextResponse.json(
        { message: err.message, errors: err.body?.errors },
        { status: err.status },
      );
    }
    return NextResponse.json(
      { message: "Unable to reach the authentication server." },
      { status: 502 },
    );
  }
}
