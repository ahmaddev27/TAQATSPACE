import { NextResponse } from "next/server";
import { serverFetch, ApiError } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types/auth";

interface CashierInvitationPreview {
  email: string;
  name: string;
  workspace_name: string;
}

/**
 * Reads the public cashier invitation preview for a given token so the accept
 * page can show the workspace + invited email before signup. No auth: the token
 * itself is the credential. The token stays server-side of this route handler.
 */
export async function GET(request: Request) {
  const token = new URL(request.url).searchParams.get("token");
  if (!token) {
    return NextResponse.json({ message: "Missing invitation token." }, { status: 400 });
  }

  try {
    const result = await serverFetch<ApiEnvelope<CashierInvitationPreview>>(
      `/cashier/invitation?token=${encodeURIComponent(token)}`,
      { auth: false },
    );
    return NextResponse.json(result.data);
  } catch (err) {
    if (err instanceof ApiError) {
      return NextResponse.json(
        { message: err.message, errors: err.body?.errors },
        { status: err.status },
      );
    }
    return NextResponse.json(
      { message: "Unable to reach the invitation server." },
      { status: 502 },
    );
  }
}
