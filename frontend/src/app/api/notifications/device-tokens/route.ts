import { NextResponse, type NextRequest } from "next/server";
import { ApiError, serverFetch } from "@/lib/api";

/**
 * BFF proxy for FCM device-token registration.
 *
 * The browser cannot reach the Laravel backend directly with the httpOnly bearer
 * cookie, so `lib/firebase/messaging.ts` POSTs/DELETEs here and `serverFetch`
 * attaches the token server-side — mirroring the existing `/api/notifications`
 * proxy. The backend exposes `POST /notifications/device-tokens` (register) and
 * `DELETE /notifications/device-tokens` (unregister).
 */

const BACKEND_PATH = "/notifications/device-tokens";

/** Body forwarded as-is, defaulting to an empty object on a parse failure. */
async function readJson(request: NextRequest): Promise<unknown> {
  try {
    return await request.json();
  } catch {
    return {};
  }
}

function toErrorResponse(err: unknown): NextResponse {
  const status = err instanceof ApiError ? err.status : 500;
  return NextResponse.json(
    { message: err instanceof Error ? err.message : "Request failed." },
    { status: status === 401 || status === 403 ? 401 : status },
  );
}

/** Register (upsert) the caller's FCM token. */
export async function POST(request: NextRequest): Promise<NextResponse> {
  const body = await readJson(request);
  try {
    await serverFetch(BACKEND_PATH, {
      method: "POST",
      body: JSON.stringify(body),
    });
    return NextResponse.json({ ok: true }, { status: 200 });
  } catch (err) {
    return toErrorResponse(err);
  }
}

/** Unregister the caller's FCM token. */
export async function DELETE(request: NextRequest): Promise<NextResponse> {
  const body = await readJson(request);
  try {
    await serverFetch(BACKEND_PATH, {
      method: "DELETE",
      body: JSON.stringify(body),
    });
    return NextResponse.json({ ok: true }, { status: 200 });
  } catch (err) {
    return toErrorResponse(err);
  }
}
