import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { serverFetch } from "@/lib/api";
import { ROLE_COOKIE, TOKEN_COOKIE } from "@/lib/auth";
import type { ApiEnvelope } from "@/lib/types/auth";

export async function POST() {
  // Best-effort backend logout; ignore failures (cookie cleared regardless).
  // When the session was opened via Taqat SSO the backend returns the provider's
  // RP-initiated logout URL so the browser can end the IdP session too. The
  // backend wraps it in the standard envelope, so read it from `data`.
  let ssoLogoutUrl: string | null = null;
  try {
    const result = await serverFetch<
      ApiEnvelope<{ sso_logout_url: string | null }>
    >("/auth/logout", { method: "POST" });
    ssoLogoutUrl = result?.data?.sso_logout_url ?? null;
  } catch {
    // no-op
  }

  const store = await cookies();
  store.delete(TOKEN_COOKIE);
  store.delete(ROLE_COOKIE);

  return NextResponse.json({ sso_logout_url: ssoLogoutUrl }, { status: 200 });
}
