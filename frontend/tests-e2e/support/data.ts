import type { APIResponse } from "@playwright/test";
import type { ApiClient } from "./api";
import { deepFind } from "./api";
import { firstArray } from "./ui";

/**
 * A short value for names and codes, new on every run: rows made by a test must
 * not collide with an earlier run that was not reseeded, and the codes the API
 * accepts are letters, digits, dash and underscore.
 */
export function unique(prefix: string): string {
  return `${prefix}-${Date.now().toString(36)}${Math.floor(Math.random() * 1296).toString(36)}`.slice(0, 40);
}

/** The smallest file the receipt uploads accept (mime and extension are what the server checks). */
export const TINY_PDF = { name: "receipt.pdf", mimeType: "application/pdf", buffer: Buffer.from("%PDF-1.4\n%%EOF\n") };

export function dateFromNow(days: number): string {
  return new Date(Date.now() + days * 86_400_000).toISOString().slice(0, 10);
}

/** The id of the record a create/update endpoint answered with. */
export function idOf(body: unknown): string {
  const id = deepFind(body, "id");
  if (typeof id !== "string") throw new Error("The response carries no id.");
  return id;
}

export async function created<T = unknown>(api: ApiClient, response: APIResponse, expected = 201): Promise<T> {
  const status = response.status();
  if (status !== expected) throw new Error(`Expected HTTP ${expected} but got ${status} from ${response.url().replace(/\?.*$/, "")}`);
  return api.json<T>(response);
}

/** The user id of a workspace member, found by e-mail through the roster's own search. */
export async function memberUserId(owner: ApiClient, email: string): Promise<string> {
  const roster = await owner.json(await owner.get("workspace/members", { search: email }));
  const row = firstArray(roster).find((item) => deepFind(item, "email") === email);
  const id = row === undefined ? undefined : deepFind(deepFind(row, "user"), "id");
  if (typeof id !== "string") throw new Error(`${email} is not in the roster.`);
  return id;
}

/** A decimal string from the API (MariaDB returns "50.00") as a number. */
export function num(value: unknown): number {
  return Number(value);
}

/** The first row of a list whose field `key` equals `value`. */
export function rowWhere(body: unknown, key: string, value: unknown): Record<string, unknown> | undefined {
  return firstArray(body).find((item) => (item as Record<string, unknown>)[key] === value) as Record<string, unknown> | undefined;
}
