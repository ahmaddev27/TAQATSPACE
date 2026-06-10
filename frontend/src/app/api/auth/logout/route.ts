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
  // TEMP DEBUG (SSO single-logout) — remove once verified. Logs to the Node
  // process output (app.log on the server).
  console.info("[sso-logout] proxy: ENTER /api/auth/logout");
  let ssoLogoutUrl: string | null = null;
  try {
    const result = await serverFetch<
      ApiEnvelope<{ sso_logout_url: string | null }>
    >("/auth/logout", { method: "POST" });
    console.info(
      "[sso-logout] proxy: RAW backend envelope =",
      JSON.stringify(result),
    );
    ssoLogoutUrl = result?.data?.sso_logout_url ?? null;
    console.info("[sso-logout] proxy: EXTRACTED sso_logout_url =", ssoLogoutUrl);
  } catch (err) {
    const status =
      err && typeof err === "object" && "status" in err
        ? (err as { status?: number }).status
        : undefined;
    console.error("[sso-logout] proxy: backend call FAILED", {
      status,
      message: err instanceof Error ? err.message : String(err),
    });
  }

  const store = await cookies();
  store.delete(TOKEN_COOKIE);
  store.delete(ROLE_COOKIE);

  console.info("[sso-logout] proxy: EXIT returning", { sso_logout_url: ssoLogoutUrl });
  return NextResponse.json({ sso_logout_url: ssoLogoutUrl }, { status: 200 });
}
