import { NextResponse } from "next/server";
import { ApiError, serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";

/**
 * BFF proxy for the Firebase chat custom-token mint.
 *
 * The browser holds the bearer in an httpOnly cookie it cannot read, so the
 * chat auth bridge (`lib/firebase/chat.ts`) POSTs here and `serverFetch`
 * attaches the token server-side, forwarding to the backend `POST /chat/token`.
 * The backend returns 503 when Firebase is unconfigured; we surface that status
 * so the client degrades to the "chat unavailable" state without throwing.
 */

const BACKEND_PATH = "/chat/token";

interface ChatToken {
  token: string;
  uid: string;
}

export async function POST(): Promise<NextResponse> {
  try {
    const res = await serverFetch<ApiEnvelope<ChatToken>>(BACKEND_PATH, {
      method: "POST",
    });
    return NextResponse.json(res.data, { status: 200 });
  } catch (err) {
    const status = err instanceof ApiError ? err.status : 500;
    return NextResponse.json(
      { message: err instanceof Error ? err.message : "Request failed." },
      { status },
    );
  }
}
