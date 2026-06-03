import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { API_BASE } from "@/lib/api";
import { TOKEN_COOKIE } from "@/lib/auth";

/**
 * GET /api/invoices/{id}/pdf
 *
 * The backend streams the invoice PDF behind a Sanctum bearer token. Browsers
 * can't attach our httpOnly cookie's token to a cross-origin `<a download>`, so
 * this same-origin handler reads the cookie server-side, calls the backend with
 * the bearer, and pipes the PDF straight back to the browser as an attachment.
 */
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string }> },
): Promise<Response> {
  const { id } = await params;

  const store = await cookies();
  const token = store.get(TOKEN_COOKIE)?.value;
  if (!token) {
    return NextResponse.json({ message: "Unauthorized" }, { status: 401 });
  }

  const base = API_BASE.replace(/\/$/, "");
  const upstream = await fetch(`${base}/invoices/${id}/pdf`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/pdf",
    },
    cache: "no-store",
  });

  if (!upstream.ok || upstream.body === null) {
    const status = upstream.status === 0 ? 502 : upstream.status;
    return NextResponse.json(
      { message: "Unable to download invoice." },
      { status },
    );
  }

  const headers = new Headers();
  headers.set(
    "Content-Type",
    upstream.headers.get("content-type") ?? "application/pdf",
  );
  headers.set(
    "Content-Disposition",
    upstream.headers.get("content-disposition") ??
      `attachment; filename="invoice-${id}.pdf"`,
  );
  const length = upstream.headers.get("content-length");
  if (length) headers.set("Content-Length", length);
  headers.set("Cache-Control", "no-store");

  return new Response(upstream.body, { status: 200, headers });
}
