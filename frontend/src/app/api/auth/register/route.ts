import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { serverFetch, ApiError } from "@/lib/api";
import { ROLE_COOKIE, TOKEN_COOKIE, TOKEN_MAX_AGE } from "@/lib/auth";
import type { ApiEnvelope, AuthPayload } from "@/lib/types/auth";

const isProd = process.env.NODE_ENV === "production";

/**
 * Forwards a multipart registration to the backend.
 * - freelancer  -> set auth cookies (logged in).
 * - workspace_owner -> do NOT set cookies (pending review).
 */
export async function POST(request: Request) {
  let formData: FormData;
  try {
    formData = await request.formData();
  } catch {
    return NextResponse.json(
      { message: "Invalid multipart form data." },
      { status: 400 },
    );
  }

  const role = formData.get("role");

  try {
    const result = await serverFetch<ApiEnvelope<AuthPayload>>("/auth/register", {
      method: "POST",
      auth: false,
      body: formData,
    });

    const { user, token, role: resolvedRole } = result.data;

    // Only freelancers are auto-authenticated on registration.
    if (resolvedRole === "freelancer") {
      const store = await cookies();
      store.set(TOKEN_COOKIE, token, {
        httpOnly: true,
        sameSite: "lax",
        secure: isProd,
        path: "/",
        maxAge: TOKEN_MAX_AGE,
      });
      store.set(ROLE_COOKIE, resolvedRole, {
        httpOnly: false,
        sameSite: "lax",
        secure: isProd,
        path: "/",
        maxAge: TOKEN_MAX_AGE,
      });
    }

    return NextResponse.json(
      { user, role: resolvedRole },
      { status: 201 },
    );
  } catch (err) {
    if (err instanceof ApiError) {
      return NextResponse.json(
        { message: err.message, errors: err.body?.errors },
        { status: err.status },
      );
    }
    return NextResponse.json(
      { message: "Unable to reach the registration server.", role },
      { status: 502 },
    );
  }
}
