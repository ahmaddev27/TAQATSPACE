import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { serverFetch, ApiError } from "@/lib/api";
import { ROLE_COOKIE, TOKEN_COOKIE, TOKEN_MAX_AGE } from "@/lib/auth";
import type { ApiEnvelope, User } from "@/lib/types/auth";

const isProd = process.env.NODE_ENV === "production";

interface CashierAcceptPayload {
  token: string;
  user: User;
}

/**
 * Completes a cashier invitation: exchanges the invite token + chosen password
 * for a Sanctum session, then sets the httpOnly cookies exactly like the SSO
 * finish handler. The token is never returned to the client — only the user +
 * role (for client-side routing).
 */
export async function POST(request: Request) {
  let body: {
    token?: string;
    name?: string;
    password?: string;
    password_confirmation?: string;
  };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ message: "Invalid request body." }, { status: 400 });
  }

  if (!body.token) {
    return NextResponse.json({ message: "Missing invitation token." }, { status: 400 });
  }

  try {
    const result = await serverFetch<ApiEnvelope<CashierAcceptPayload>>("/cashier/accept", {
      method: "POST",
      auth: false,
      body: JSON.stringify({
        token: body.token,
        name: body.name,
        password: body.password,
        password_confirmation: body.password_confirmation,
      }),
    });

    const { user, token } = result.data;

    const store = await cookies();
    store.set(TOKEN_COOKIE, token, {
      httpOnly: true,
      sameSite: "lax",
      secure: isProd,
      path: "/",
      maxAge: TOKEN_MAX_AGE,
    });
    store.set(ROLE_COOKIE, user.role, {
      httpOnly: false,
      sameSite: "lax",
      secure: isProd,
      path: "/",
      maxAge: TOKEN_MAX_AGE,
    });

    // Never expose the token to the client.
    return NextResponse.json({ user, role: user.role });
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
