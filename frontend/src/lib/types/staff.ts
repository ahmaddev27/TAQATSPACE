/** POS permissions an owner can grant staff (mirrors the backend enum). */
export type PosPermission =
  | "pos_sell"
  | "pos_refund"
  | "pos_manage_products"
  | "pos_view_reports";

export const POS_PERMISSIONS: PosPermission[] = [
  "pos_sell",
  "pos_refund",
  "pos_manage_products",
  "pos_view_reports",
];

/** Predefined staff permission groups (mirrors the backend `StaffGroup` enum). */
export type StaffGroup = "cashier" | "hospitality" | "finance_supervisor";

export const STAFF_GROUPS: StaffGroup[] = [
  "cashier",
  "hospitality",
  "finance_supervisor",
];

/** The group → permissions map; keep in sync with `StaffGroup::permissions()`. */
export const STAFF_GROUP_PERMISSIONS: Record<StaffGroup, PosPermission[]> = {
  cashier: ["pos_sell", "pos_view_reports"],
  hospitality: ["pos_sell", "pos_manage_products"],
  finance_supervisor: ["pos_refund", "pos_view_reports"],
};

export const DEFAULT_STAFF_GROUP: StaffGroup = "cashier";

export interface Staff {
  id: string;
  name: string;
  email: string;
  status: string;
  staff_group: StaffGroup;
  permissions: PosPermission[];
  created_at: string | null;
}

export interface StaffInvitation {
  id: string;
  email: string;
  name: string | null;
  staff_group: StaffGroup;
  permissions: PosPermission[];
  expires_at: string | null;
  created_at: string | null;
}

export interface StaffResult {
  staff: Staff[];
  invitations: StaffInvitation[];
}

export interface InviteStaffInput {
  email: string;
  name?: string;
  staff_group?: StaffGroup;
  permissions?: PosPermission[];
}
