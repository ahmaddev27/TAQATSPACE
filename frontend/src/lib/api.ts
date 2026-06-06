// This module is server-only: it imports `next/headers` (cookies), which Next
// refuses to bundle into client components. No `server-only` package needed.
import { cookies } from "next/headers";
import { getLocale } from "next-intl/server";
import { TOKEN_COOKIE } from "./auth";
import type { ApiErrorBody } from "./types/auth";

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

/** Locale sent to the backend when the request locale cannot be resolved. */
const DEFAULT_LOCALE = "ar";

/** Base class for any failed backend call. */
export class ApiError extends Error {
  readonly status: number;
  readonly body: ApiErrorBody | undefined;

  constructor(status: number, message: string, body?: ApiErrorBody) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.body = body;
  }
}

export class UnauthorizedError extends ApiError {
  constructor(message = "Unauthorized", body?: ApiErrorBody) {
    super(401, message, body);
    this.name = "UnauthorizedError";
  }
}

export class ForbiddenError extends ApiError {
  constructor(message: string, body?: ApiErrorBody) {
    super(403, message, body);
    this.name = "ForbiddenError";
  }
}

export class ValidationError extends ApiError {
  readonly errors: Record<string, string[]>;

  constructor(message: string, errors: Record<string, string[]>, body?: ApiErrorBody) {
    super(422, message, body);
    this.name = "ValidationError";
    this.errors = errors;
  }
}

async function readToken(): Promise<string | undefined> {
  const store = await cookies();
  return store.get(TOKEN_COOKIE)?.value;
}

/**
 * Resolve the active next-intl locale so the backend can localise its
 * responses (validation errors, messages). Stays defensive: outside a request
 * scope `getLocale()` can throw, so we fall back to the app default.
 */
async function readLocale(): Promise<string> {
  try {
    return (await getLocale()) || DEFAULT_LOCALE;
  } catch {
    return DEFAULT_LOCALE;
  }
}

function buildUrl(path: string): string {
  if (path.startsWith("http")) return path;
  const base = API_BASE.replace(/\/$/, "");
  const suffix = path.startsWith("/") ? path : `/${path}`;
  return `${base}${suffix}`;
}

async function toError(response: Response): Promise<ApiError> {
  let body: ApiErrorBody | undefined;
  try {
    body = (await response.json()) as ApiErrorBody;
  } catch {
    body = undefined;
  }
  const message = body?.message ?? response.statusText ?? "Request failed";

  switch (response.status) {
    case 401:
      return new UnauthorizedError(message, body);
    case 403:
      return new ForbiddenError(message, body);
    case 422:
      return new ValidationError(message, body?.errors ?? {}, body);
    default:
      return new ApiError(response.status, message, body);
  }
}

interface ServerFetchInit extends Omit<RequestInit, "body"> {
  body?: BodyInit | null;
  /** When false, skip attaching the bearer token (e.g. login/register). */
  auth?: boolean;
}

/**
 * Server-side fetch against the Laravel backend.
 * Attaches the bearer token from the httpOnly cookie, normalises headers,
 * and maps non-2xx responses to typed errors.
 */
export async function serverFetch<T>(
  path: string,
  init: ServerFetchInit = {},
): Promise<T> {
  const { auth = true, headers, body, ...rest } = init;

  const finalHeaders = new Headers(headers);
  finalHeaders.set("Accept", "application/json");

  if (!finalHeaders.has("Accept-Language")) {
    finalHeaders.set("Accept-Language", await readLocale());
  }

  const isFormData = typeof FormData !== "undefined" && body instanceof FormData;
  if (!isFormData && !finalHeaders.has("Content-Type") && body != null) {
    finalHeaders.set("Content-Type", "application/json");
  }

  if (auth) {
    const token = await readToken();
    if (token) finalHeaders.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(buildUrl(path), {
    ...rest,
    headers: finalHeaders,
    body,
    cache: "no-store",
  });

  if (!response.ok) {
    throw await toError(response);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

export { API_BASE };
