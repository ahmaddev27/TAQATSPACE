/** Audit log (NFR-AUDIT) — mirrors the AuditLogResource payload. */

export type AuditActorType = "user" | "system" | "router";

export interface AuditLogEntry {
  id: number;
  workspace_id: string | null;
  actor_type: AuditActorType;
  actor: { id: string; name: string; email: string } | null;
  action: string;
  subject_type: string | null;
  subject_id: string | null;
  reason: string | null;
  changes: Record<string, unknown> | null;
  ip: string | null;
  request_id: string | null;
  created_at: string | null;
}

export interface AuditLogMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface AuditLogResult {
  logs: AuditLogEntry[];
  meta: AuditLogMeta;
}

export interface AuditLogParams {
  actor_id?: string;
  action?: string;
  from?: string;
  to?: string;
  subject_type?: string;
  subject_id?: string;
  reason?: string;
  per_page?: number;
  page?: number;
}
