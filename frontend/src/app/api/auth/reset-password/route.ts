import { NextResponse } from "next/server";
import { serverFetch, ApiError } from "@/lib/api";

interface ResetBody {
  token?: string;
  email?: string;
  password?: string;
  password_confirmation?: string;
}

export async function POST(request: Request) {
  let body: ResetBody;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ message: "Invalid request body." }, { status: 400 });
  }

  try {
    const result = await serverFetch<{ message?: string }>("/auth/reset-password", {
      method: "POST",
      auth: false,
      body: JSON.stringify(body),
    });
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
