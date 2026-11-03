import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type { AuditLogParams, AuditLogResult } from "@/lib/types/audit";

/** Server-only reader: audit log of THIS owner's workspace (read-only). */
export async function ownerAuditLogs(
  params: AuditLogParams = {},
): Promise<AuditLogResult> {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();

  const res = await serverFetch<ApiEnvelope<AuditLogResult>>(
    `/workspace/audit-logs${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}
