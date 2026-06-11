import { NextResponse } from "next/server";
import { serverFetch, ApiError } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types/auth";

/**
 * Forwards a "new chat message" alert to the backend (authenticated via the
 * httpOnly bearer cookie), which raises the recipient's bell + native push.
 * Fire-and-forget: the Firestore message is already delivered, so any failure
 * here is swallowed by the caller.
 */
export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ notified: false }, { status: 400 });
  }

  try {
    const result = await serverFetch<ApiEnvelope<{ notified: boolean }>>(
      "/chat/notify",
      {
        method: "POST",
        body: JSON.stringify(body),
      },
    );
    return NextResponse.json({ notified: result.data.notified });
  } catch (err) {
    const status = err instanceof ApiError ? err.status : 502;
    return NextResponse.json({ notified: false }, { status });
  }
}
