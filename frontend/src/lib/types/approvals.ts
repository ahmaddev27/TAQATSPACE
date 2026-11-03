/** Approvals framework (FR-SA-05) — mirrors the ApprovalRequestResource payload. */

export type ApprovalType = "capacity" | "working_hours";

export type ApprovalStatus = "pending" | "approved" | "rejected" | "cancelled";

export interface ApprovalRequestEntry {
  id: string;
  type: ApprovalType;
  status: ApprovalStatus;
  workspace?: { id: string; name: string };
  requester?: { id: string; name: string; email: string } | null;
  /** Handler-described change; shape depends on the type. */
  change: { current: unknown; proposed: unknown };
  decision_reason: string | null;
  decided_at: string | null;
  created_at: string | null;
}

export interface ApprovalListMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface ApprovalListResult {
  requests: ApprovalRequestEntry[];
  meta: ApprovalListMeta;
}

export interface ApprovalListParams {
  status?: ApprovalStatus;
  type?: ApprovalType;
  per_page?: number;
  page?: number;
}
