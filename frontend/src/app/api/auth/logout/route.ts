import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { serverFetch } from "@/lib/api";
import { ROLE_COOKIE, TOKEN_COOKIE } from "@/lib/auth";

export async function POST() {
  // Best-effort backend logout; ignore failures (cookie cleared regardless).
  try {
    await serverFetch<void>("/auth/logout", { method: "POST" });
  } catch {
    // no-op
  }

  const store = await cookies();
  store.delete(TOKEN_COOKIE);
  store.delete(ROLE_COOKIE);

  return new NextResponse(null, { status: 204 });
}
