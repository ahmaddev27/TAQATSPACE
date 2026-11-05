import { request, type APIRequestContext, type APIResponse } from "@playwright/test";
import { apiBase, demoPassword, emailOf, type AccountKey } from "./accounts";

/**
 * A thin client for the Laravel API, used to prepare and verify state around the
 * browser steps. It logs in through the same /auth/login route the app uses.
 */
export class ApiClient {
  private constructor(
    private readonly ctx: APIRequestContext,
    private readonly token: string,
  ) {}

  /** Never put the response body in an error: it would carry the token. */
  static async login(key: AccountKey, extra: Record<string, unknown> = {}): Promise<ApiClient> {
    const ctx = await request.newContext({ baseURL: `${apiBase()}/`, extraHTTPHeaders: { Accept: "application/json" } });
    const res = await ctx.post("auth/login", {
      data: { email: emailOf(key), password: demoPassword(), ...extra },
      failOnStatusCode: false,
    });
    if (!res.ok()) throw new Error(`API login failed for ${key}: HTTP ${res.status()}`);
    const body = (await res.json()) as { data: { token: string; two_factor_required?: boolean } };
    if (body.data.two_factor_required) throw new Error(`API login for ${key} needs a two-factor code.`);
    return new ApiClient(ctx, body.data.token);
  }

  private headers(): Record<string, string> {
    return { Authorization: `Bearer ${this.token}` };
  }

  get(path: string, params?: Record<string, string | number>): Promise<APIResponse> {
    return this.ctx.get(path.replace(/^\//, ""), { headers: this.headers(), params, failOnStatusCode: false });
  }

  post(path: string, data: unknown = {}): Promise<APIResponse> {
    return this.ctx.post(path.replace(/^\//, ""), { headers: this.headers(), data, failOnStatusCode: false });
  }

  put(path: string, data: unknown = {}): Promise<APIResponse> {
    return this.ctx.put(path.replace(/^\//, ""), { headers: this.headers(), data, failOnStatusCode: false });
  }

  delete(path: string, data?: unknown): Promise<APIResponse> {
    return this.ctx.delete(path.replace(/^\//, ""), { headers: this.headers(), data, failOnStatusCode: false });
  }

  /** A multipart upload, for the endpoints that take a file. */
  postFile(path: string, fields: Record<string, string>, file: { name: string; mimeType: string; buffer: Buffer }): Promise<APIResponse> {
    return this.ctx.post(path.replace(/^\//, ""), {
      headers: this.headers(),
      multipart: { ...fields, receipt: file },
      failOnStatusCode: false,
    });
  }

  /** A multipart upload under a custom field name (e.g. the import `file`). */
  postUpload(path: string, field: string, fields: Record<string, string>, file: { name: string; mimeType: string; buffer: Buffer }): Promise<APIResponse> {
    return this.ctx.post(path.replace(/^\//, ""), {
      headers: this.headers(),
      multipart: { ...fields, [field]: file },
      failOnStatusCode: false,
    });
  }

  async json<T = unknown>(response: APIResponse): Promise<T> {
    return (await response.json()) as T;
  }

  dispose(): Promise<void> {
    return this.ctx.dispose();
  }
}

/** Finds the first value stored under `key` anywhere in a JSON document. */
export function deepFind(value: unknown, key: string): unknown {
  if (value === null || typeof value !== "object") return undefined;
  const record = value as Record<string, unknown>;
  if (key in record) return record[key];
  for (const child of Object.values(record)) {
    const found = deepFind(child, key);
    if (found !== undefined) return found;
  }
  return undefined;
}
