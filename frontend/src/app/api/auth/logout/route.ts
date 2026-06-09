import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { serverFetch } from "@/lib/api";
import { ROLE_COOKIE, TOKEN_COOKIE } from "@/lib/auth";

export async function POST() {
  // Best-effort backend logout; ignore failures (cookie cleared regardless).
  // When the session was opened via Taqat SSO the backend returns the provider's
  // RP-initiated logout URL so the browser can end the IdP session too.
  let ssoLogoutUrl: string | null = null;
  try {
    const result = await serverFetch<{ sso_logout_url: string | null }>(
      "/auth/logout",
      { method: "POST" },
    );
    ssoLogoutUrl = result?.sso_logout_url ?? null;
    // TEMP DEBUG (SSO single-logout diagnosis) — remove once verified.
    console.info("[sso-logout] proxy: backend returned", {
      sso_logout_url: ssoLogoutUrl,
    });
  } catch (err) {
    // TEMP DEBUG (SSO single-logout diagnosis) — remove once verified.
    // serverFetch throws on any non-2xx; a 401 here means the backend logout()
    // never ran (auth middleware blocked it) — so no backend log appears.
    const status =
      err && typeof err === "object" && "status" in err
        ? (err as { status?: number }).status
        : undefined;
    console.error("[sso-logout] proxy: backend call failed", {
      status,
      message: err instanceof Error ? err.message : String(err),
    });
  }

  const store = await cookies();
  store.delete(TOKEN_COOKIE);
  store.delete(ROLE_COOKIE);

  return NextResponse.json({ sso_logout_url: ssoLogoutUrl }, { status: 200 });
}
