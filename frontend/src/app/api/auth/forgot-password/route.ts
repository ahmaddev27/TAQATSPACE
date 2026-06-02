import { NextResponse } from "next/server";
import { serverFetch, ApiError } from "@/lib/api";

export async function POST(request: Request) {
  let body: { email?: string };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ message: "Invalid request body." }, { status: 400 });
  }

  try {
    const result = await serverFetch<{ message?: string }>(
      "/auth/forgot-password",
      {
        method: "POST",
        auth: false,
        body: JSON.stringify({ email: body.email }),
      },
    );
    return NextResponse.json({ message: result?.message ?? "" });
  } catch (err) {
    if (err instanceof ApiError) {
      return NextResponse.json(
        { message: err.message, errors: err.body?.errors },
        { status: err.status },
      );
    }
    return NextResponse.json(
      { message: "Unable to reach the server." },
      { status: 502 },
    );
  }
}
