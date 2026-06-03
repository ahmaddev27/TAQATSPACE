import { serverFetch } from "@/lib/api";
import type {
  ApiEnvelope,
  Member,
  MemberDetail,
  MemberListResult,
} from "@/lib/types";

export interface ListMembersParams {
  status?: "active" | "suspended" | "all";
  search?: string;
  sort?: "join_date" | "name" | "status";
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
}

/**
 * Owner: paginated roster of THIS workspace's members. Response shape is
 * `{ members, meta }` (not the standard resource paginator).
 */
export async function listMembers(
  params: ListMembersParams = {},
): Promise<MemberListResult> {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value == null || value === "") continue;
    search.set(key, String(value));
  }
  const qs = search.toString();

  const res = await serverFetch<ApiEnvelope<MemberListResult>>(
    `/workspace/members${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}

/** Owner: full detail for one member of their workspace. */
export async function showMember(userId: string): Promise<MemberDetail> {
  const res = await serverFetch<ApiEnvelope<MemberDetail>>(
    `/workspace/members/${userId}`,
  );
  return res.data;
}

/** Owner: activate or suspend a member (suspend frees their seat). */
export async function updateMemberStatus(
  userId: string,
  status: "active" | "suspended",
  note?: string,
): Promise<Member> {
  const res = await serverFetch<ApiEnvelope<Member>>(
    `/workspace/members/${userId}/status`,
    {
      method: "PUT",
      body: JSON.stringify(note == null ? { status } : { status, note }),
    },
  );
  return res.data;
}
