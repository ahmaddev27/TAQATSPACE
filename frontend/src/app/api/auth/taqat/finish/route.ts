import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { serverFetch, ApiError } from "@/lib/api";
import { ROLE_COOKIE, TOKEN_COOKIE, TOKEN_MAX_AGE } from "@/lib/auth";
import type { ApiEnvelope, AuthPayload } from "@/lib/types/auth";

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
    const result = await serverFetch<ApiEnvelope<AuthPayload>>("/auth/taqat/exchange", {
      method: "POST",
      auth: false,
      body: JSON.stringify({ code: body.code }),
    });

    const { user, token, role } = result.data;

    const store = await cookies();
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
