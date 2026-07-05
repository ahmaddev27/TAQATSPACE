/** POS permissions an owner can grant a cashier (mirrors the backend enum). */
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

/** The default grant a freshly-invited cashier receives (mirrors the backend). */
export const DEFAULT_CASHIER_PERMISSIONS: PosPermission[] = [
  "pos_sell",
  "pos_view_reports",
];

export interface Cashier {
  id: string;
  name: string;
  email: string;
  status: string;
  permissions: PosPermission[];
  created_at: string | null;
}

export interface CashierInvitation {
  id: string;
  email: string;
  name: string | null;
  permissions: PosPermission[];
  expires_at: string | null;
  created_at: string | null;
}

export interface CashiersResult {
  cashiers: Cashier[];
  invitations: CashierInvitation[];
}

export interface InviteCashierInput {
  email: string;
  name?: string;
  permissions?: PosPermission[];
}
