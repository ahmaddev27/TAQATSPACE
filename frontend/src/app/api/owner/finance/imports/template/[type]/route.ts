import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { API_BASE } from "@/lib/api";
import { TOKEN_COOKIE } from "@/lib/auth";
import { IMPORT_TYPES, type ImportType } from "@/lib/types/financeImports";

function isImportType(value: string): value is ImportType {
  return (IMPORT_TYPES as string[]).includes(value);
}

/**
 * GET /api/owner/finance/imports/template/{type}
 *
 * Same-origin proxy for the XLSX template download: the browser cannot attach
 * the httpOnly token cookie to a cross-origin request, so this handler reads it
 * server-side and pipes the file back as an attachment.
 */
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ type: string }> },
): Promise<Response> {
  const { type } = await params;
  if (!isImportType(type)) {
    return NextResponse.json({ message: "Unknown import type." }, { status: 404 });
  }

  const token = (await cookies()).get(TOKEN_COOKIE)?.value;
  if (!token) {
    return NextResponse.json({ message: "Unauthorized" }, { status: 401 });
  }

  let upstream: Response;
  try {
    upstream = await fetch(`${API_BASE.replace(/\/$/, "")}/workspace/finance/imports/template/${type}`, {
      headers: { Authorization: `Bearer ${token}` },
      cache: "no-store",
    });
  } catch {
    return NextResponse.json({ message: "Unable to reach the import service." }, { status: 502 });
  }

  if (!upstream.ok || upstream.body === null) {
    const status = [401, 403, 404].includes(upstream.status) ? upstream.status : 500;
    return NextResponse.json({ message: "Unable to download the template." }, { status });
  }

  return new Response(upstream.body, {
    status: 200,
    headers: {
      "Content-Type": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      "Content-Disposition": `attachment; filename="${type}-template.xlsx"`,
      "Cache-Control": "no-store",
    },
  });
}
