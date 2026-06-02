import { NextResponse } from "next/server";
import { serverFetch, ApiError } from "@/lib/api";
import type { ApiEnvelope, User } from "@/lib/types/auth";

export async function GET() {
  try {
    const result = await serverFetch<ApiEnvelope<{ user: User }>>("/auth/me", {
      method: "GET",
    });
    return NextResponse.json({ user: result.data.user });
  } catch (err) {
    const status = err instanceof ApiError ? err.status : 401;
    return NextResponse.json(
      { message: err instanceof Error ? err.message : "Unauthorized" },
      { status: status === 401 || status === 403 ? 401 : status },
    );
  }
}
