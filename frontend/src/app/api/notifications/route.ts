import { NextResponse, type NextRequest } from "next/server";
import { ApiError } from "@/lib/api";
import { listNotifications } from "@/lib/api/notifications";

/**
 * Client-pollable proxy for the bell. `serverFetch` (called inside
 * `listNotifications`) reads the httpOnly bearer cookie, so the token never
 * reaches the browser. The client `NotificationBell` polls this route to keep
 * its unread badge and recent feed fresh.
 *
 * Query params: `?unread=1` to restrict to unread, `?per_page=N` to cap size,
 * `?page=N` for the feed's "Load more" pagination.
 */
export async function GET(request: NextRequest): Promise<NextResponse> {
  const { searchParams } = request.nextUrl;
  const unread = searchParams.get("unread") === "1";
  const perPageRaw = Number(searchParams.get("per_page"));
  const perPage =
    Number.isFinite(perPageRaw) && perPageRaw > 0
      ? Math.min(perPageRaw, 50)
      : 8;
  // Forward the page so the full feed can paginate, not just the bell's first page.
  const pageRaw = Number(searchParams.get("page"));
  const page = Number.isFinite(pageRaw) && pageRaw > 0 ? pageRaw : undefined;

  try {
    const result = await listNotifications({ unread, per_page: perPage, page });
    return NextResponse.json(result, {
      headers: { "Cache-Control": "no-store" },
    });
  } catch (err) {
    const status = err instanceof ApiError ? err.status : 500;
    return NextResponse.json(
      { message: err instanceof Error ? err.message : "Request failed." },
      { status: status === 401 || status === 403 ? 401 : status },
    );
  }
}
