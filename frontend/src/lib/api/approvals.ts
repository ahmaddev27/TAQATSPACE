import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type {
  ApprovalListParams,
  ApprovalListResult,
} from "@/lib/types/approvals";

/** Server-only reader: the platform-wide approval queue (admin). */
export async function adminApprovals(
  params: ApprovalListParams = {},
): Promise<ApprovalListResult> {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();

  const res = await serverFetch<ApiEnvelope<ApprovalListResult>>(
    `/admin/approvals${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}
