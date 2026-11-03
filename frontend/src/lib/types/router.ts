/** Connection state shown to the owner (derived server-side from the last sync). */
export type RouterStatus = "pending" | "online" | "offline" | "locked" | "disabled";

export type RouterCommandStatus =
  | "requested"
  | "delivered"
  | "acknowledged"
  | "failed"
  | "expired";

/** One platform-to-router command; the payload is never sent to the browser. */
export interface RouterCommand {
  id: string;
  verb: string;
  status: RouterCommandStatus;
  attempts: number;
  error: string | null;
  created_at: string | null;
  delivered_at: string | null;
  acknowledged_at: string | null;
}

export interface RouterScriptUpdate {
  available: boolean;
  current_version: string | null;
  latest_version: string;
  mode: string | null;
}

export interface OwnerRouter {
  id: string;
  name: string;
  status: RouterStatus;
  last_seen_at: string | null;
  serial_number: string | null;
  routeros_version: string | null;
  script_version: string | null;
  mode: string | null;
  workspace_code: string;
  endpoint: string;
  /** Present on the detail response only. */
  commands?: RouterCommand[];
  script_update?: RouterScriptUpdate;
}

/** Install command shown once; valid for 30 minutes. */
export interface RouterInstallToken {
  command: string;
  url: string;
  expires_at: string;
}

/** A rotated key, shown once. */
export interface RouterRotatedKey {
  id: string;
  key: string;
  previous_key_expires_at: string | null;
}
