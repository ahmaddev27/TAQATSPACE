import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { API_BASE } from "@/lib/api";
import { TOKEN_COOKIE } from "@/lib/auth";

/** Export datasets the backend can stream as CSV. */
const EXPORT_TYPES = ["invoices", "subscriptions", "users", "workspaces"] as const;
type ExportType = (typeof EXPORT_TYPES)[number];

function isExportType(value: string): value is ExportType {
  return (EXPORT_TYPES as readonly string[]).includes(value);
}

/** `YYYY-MM-DD` for the download filename. */
function today(): string {
  return new Date().toISOString().slice(0, 10);
}

/**
 * GET /api/admin/exports/{type}?<filters>
 *
 * The backend streams admin CSV exports behind a Sanctum bearer token. The
 * browser can't attach our httpOnly cookie's token to a cross-origin
 * `<a download>`, so this same-origin handler reads the cookie server-side,
 * forwards the list filters as a query string, calls the backend with the
 * bearer, and pipes the CSV straight back as a file attachment.
 */
export async function GET(
  request: Request,
  { params }: { params: Promise<{ type: string }> },
): Promise<Response> {
  const { type } = await params;
  if (!isExportType(type)) {
    return NextResponse.json({ message: "Unknown export type." }, { status: 404 });
  }

  const store = await cookies();
  const token = store.get(TOKEN_COOKIE)?.value;
  if (!token) {
    return NextResponse.json({ message: "Unauthorized" }, { status: 401 });
  }

  // Carry through the admin list filters (status, search, etc.) untouched.
  const query = new URL(request.url).search;
  const base = API_BASE.replace(/\/$/, "");

  let upstream: Response;
  try {
    upstream = await fetch(`${base}/admin/exports/${type}${query}`, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "text/csv",
      },
      cache: "no-store",
    });
  } catch {
    return NextResponse.json(
      { message: "Unable to reach the export service." },
      { status: 502 },
    );
  }

  if (!upstream.ok || upstream.body === null) {
    if (upstream.status === 401 || upstream.status === 403) {
      return NextResponse.json({ message: "Unauthorized" }, { status: upstream.status });
    }
    return NextResponse.json(
      { message: "Unable to generate the export." },
      { status: 500 },
    );
  }

  const headers = new Headers();
  headers.set("Content-Type", upstream.headers.get("content-type") ?? "text/csv");
  headers.set(
    "Content-Disposition",
    `attachment; filename="taqat-${type}-${today()}.csv"`,
  );
  const length = upstream.headers.get("content-length");
  if (length) headers.set("Content-Length", length);
  headers.set("Cache-Control", "no-store");

  return new Response(upstream.body, { status: 200, headers });
}
