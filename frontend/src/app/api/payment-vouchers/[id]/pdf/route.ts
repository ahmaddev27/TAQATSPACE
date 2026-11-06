import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { API_BASE } from "@/lib/api";
import { TOKEN_COOKIE } from "@/lib/auth";

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/**
 * GET /api/payment-vouchers/{id}/pdf
 *
 * Same-origin proxy for the payment voucher PDF: the bearer token lives in an
 * httpOnly cookie, so the handler reads it server-side and pipes the PDF back.
 */
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string }> },
): Promise<Response> {
  const { id } = await params;

  // Refuse before any upstream call: the id goes straight into the upstream path.
  if (!UUID.test(id)) {
    return NextResponse.json({ message: "Payment voucher not found." }, { status: 404 });
  }

  const token = (await cookies()).get(TOKEN_COOKIE)?.value;
  if (!token) {
    return NextResponse.json({ message: "Unauthorized" }, { status: 401 });
  }

  const base = API_BASE.replace(/\/$/, "");
  const upstream = await fetch(`${base}/workspace/finance/payment-vouchers/${id}/pdf`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/pdf" },
    cache: "no-store",
  });

  if (!upstream.ok || upstream.body === null) {
    return NextResponse.json({ message: "Unable to download payment voucher." }, { status: upstream.status || 502 });
  }

  const headers = new Headers();
  headers.set("Content-Type", upstream.headers.get("content-type") ?? "application/pdf");
  headers.set(
    "Content-Disposition",
    upstream.headers.get("content-disposition") ?? `attachment; filename="payment-voucher-${id}.pdf"`,
  );
  headers.set("Cache-Control", "no-store");

  return new Response(upstream.body, { status: 200, headers });
}
