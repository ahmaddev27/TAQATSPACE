import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type {
  OwnerRouter,
  RouterApiLogParams,
  RouterApiLogResult,
} from "@/lib/types/router";

/**
 * Server-only reader for the owner's router (with recent commands). The first
 * router is the workspace's gateway; `null` means none exists yet. A workspace
 * without the `network` flag gets a 403, which the caller turns into a 404.
 */
export async function ownerRouter(): Promise<OwnerRouter | null> {
  const list = await serverFetch<ApiEnvelope<OwnerRouter[]>>("/workspace/routers");
  const first = list.data[0];
  if (!first) return null;

  const detail = await serverFetch<ApiEnvelope<OwnerRouter>>(`/workspace/routers/${first.id}`);
  return detail.data;
}

/** Server-only: the workspace's first router id, or `null` when none exists. */
export async function ownerRouterId(): Promise<string | null> {
  const list = await serverFetch<ApiEnvelope<OwnerRouter[]>>("/workspace/routers");
  return list.data[0]?.id ?? null;
}

/** Server-only reader of one router's API request log. */
export async function routerApiLogs(
  routerId: string,
  params: RouterApiLogParams = {},
): Promise<RouterApiLogResult> {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();

  const res = await serverFetch<ApiEnvelope<RouterApiLogResult>>(
    `/workspace/routers/${routerId}/logs${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}
