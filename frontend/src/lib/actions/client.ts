"use server";

import { ApiError, ValidationError, serverFetch } from "@/lib/api";

/** Discriminated result returned by every feature Server Action. */
export type ActionResult<T = unknown> =
  | { ok: true; data: T; message?: string }
  | {
      ok: false;
      message: string;
      /** Field-level validation errors (422), keyed by input name. */
      errors?: Record<string, string[]>;
      status: number;
    };

type Method = "POST" | "PUT" | "PATCH" | "DELETE";

interface MutateOptions {
  /** HTTP method; defaults to POST. */
  method?: Method;
  /** JSON-serialisable body. Mutually exclusive with `formData`. */
  body?: unknown;
  /** Multipart body for file uploads. Mutually exclusive with `body`. */
  formData?: FormData;
}

/**
 * Perform an authenticated mutation against the Laravel backend from a Server
 * Action. Reads the bearer cookie (via `serverFetch`), sends the request, and
 * normalises both success and failure into a single `ActionResult` shape so
 * client components never see a thrown error or the raw token.
 *
 * Feature actions compose on top of this; they own validation + `revalidate`.
 */
export async function authedMutate<T = unknown>(
  path: string,
  options: MutateOptions = {},
): Promise<ActionResult<T>> {
  const { method = "POST", body, formData } = options;

  try {
    const envelope = await serverFetch<{ data?: T; message?: string }>(path, {
      method,
      body: formData ?? (body != null ? JSON.stringify(body) : undefined),
    });

    return {
      ok: true,
      data: (envelope?.data ?? undefined) as T,
      message: envelope?.message,
    };
  } catch (error) {
    return toFailure(error);
  }
}

function toFailure(error: unknown): ActionResult<never> {
  if (error instanceof ValidationError) {
    return {
      ok: false,
      status: 422,
      message: error.message,
      errors: error.errors,
    };
  }

  if (error instanceof ApiError) {
    return { ok: false, status: error.status, message: error.message };
  }

  return {
    ok: false,
    status: 500,
    message: error instanceof Error ? error.message : "Request failed.",
  };
}
