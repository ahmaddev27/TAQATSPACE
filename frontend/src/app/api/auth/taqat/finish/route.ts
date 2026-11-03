import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { serverFetch, ApiError } from "@/lib/api";
import {
  PENDING_TWO_FACTOR_COOKIE,
  ROLE_COOKIE,
  TOKEN_COOKIE,
  TOKEN_MAX_AGE,
} from "@/lib/auth";
import type {
  ApiEnvelope,
  AuthPayload,
  TwoFactorChallengePayload,
} from "@/lib/types/auth";

const isProd = process.env.NODE_ENV === "production";

/**
 * Redeems the one-time SSO exchange code for a Sanctum token, then sets the
 * httpOnly cookies exactly like the email/password login handler. The token is
 * never returned to the client — only the role (for client-side routing).
 */
export async function POST(request: Request) {
  let body: { code?: string };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ message: "Invalid request body." }, { status: 400 });
  }

  if (!body.code) {
    return NextResponse.json({ message: "Missing exchange code." }, { status: 400 });
  }

  try {
    const result = await serverFetch<
      ApiEnvelope<AuthPayload | TwoFactorChallengePayload>
    >("/auth/taqat/exchange", {
      method: "POST",
      auth: false,
      body: JSON.stringify({ code: body.code }),
    });

    const store = await cookies();

    if ("two_factor_required" in result.data) {
      // Park the challenge-only token until the 2FA code is verified.
      store.set(PENDING_TWO_FACTOR_COOKIE, result.data.token, {
        httpOnly: true,
        sameSite: "lax",
        secure: isProd,
        path: "/",
        maxAge: 60 * 15,
      });
      return NextResponse.json({ two_factor_required: true });
    }

    const { user, token, role } = result.data;

    store.set(TOKEN_COOKIE, token, {
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

    // Never expose the token to the client.
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
