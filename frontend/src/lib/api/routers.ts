import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type { OwnerRouter } from "@/lib/types/router";

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
