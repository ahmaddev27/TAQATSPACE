"use server";

import { revalidatePath } from "next/cache";
import { serverFetch } from "@/lib/api";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type {
  ApiEnvelope,
  BroadcastInput,
  BroadcastResult,
  Member,
  Package,
  Seat,
  SeatType,
  SubscriptionStatus,
  Workspace,
  WorkspaceMessagingConfig,
  WorkspaceMessagingUpdateInput,
} from "@/lib/types";
import type {
  Expense,
  ExpenseCategory,
  Resource,
  ResourceStatus,
  ResourceType,
} from "@/lib/types/management";
import type {
  Cashier,
  CashierInvitation,
  InviteCashierInput,
  PosPermission,
} from "@/lib/types/cashier";

/* -------------------------------------------------------------------------- */
/*  Revalidation                                                              */
/* -------------------------------------------------------------------------- */

/**
 * Owner pages are rendered under both `/owner/*` and `/<locale>/owner/*`.
 * `revalidatePath` with `"layout"` covers the locale segment, so a single call
 * refreshes the page regardless of the active locale prefix.
 */
function revalidateOwner(segment: string): void {
  revalidatePath(`/[locale]/(dashboard)/owner/${segment}`, "page");
}

/* -------------------------------------------------------------------------- */
/*  Workspace resolution                                                      */
/* -------------------------------------------------------------------------- */

/**
 * Resolve the authenticated owner's full workspace.
 *
 * The backend exposes no read endpoint for "my workspace"; `/auth/me` and the
 * dashboard aggregate both omit it. `PUT /workspace/settings` accepts a fully
 * optional body (all rules are `sometimes`), so an empty update is a safe,
 * side-effect-free way to fetch the current workspace (id, status, settings,
 * photos) for any status — including `pending`, which the public `show`
 * endpoint hides. Returns `null` when the owner has not created a workspace.
 */
export async function getOwnerWorkspace(): Promise<Workspace | null> {
  try {
    const res = await serverFetch<ApiEnvelope<Workspace>>(
      "/workspace/settings",
      { method: "PUT", body: JSON.stringify({}) },
    );
    return res.data;
  } catch {
    return null;
  }
}

/* -------------------------------------------------------------------------- */
/*  Members                                                                   */
/* -------------------------------------------------------------------------- */

export async function updateMemberStatus(
  userId: string,
  status: SubscriptionStatus,
  note?: string,
): Promise<ActionResult<Member>> {
  const body = note == null ? { status } : { status, note };
  const result = await authedMutate<Member>(
    `/workspace/members/${userId}/status`,
    { method: "PUT", body },
  );
  if (result.ok) {
    revalidateOwner("members");
    revalidateOwner("");
  }
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Subscriptions                                                             */
/* -------------------------------------------------------------------------- */

/**
 * Renew a subscription: the backend extends the period by one plan cycle,
 * reactivates it, and raises the next pending invoice for the new term.
 */
export async function renewSubscription(
  subscriptionId: string,
): Promise<ActionResult> {
  const result = await authedMutate(
    `/workspace/subscriptions/${subscriptionId}/renew`,
    { method: "PUT" },
  );
  if (result.ok) {
    revalidateOwner("subscriptions");
    revalidateOwner("members");
    revalidateOwner("invoices");
    revalidateOwner("");
  }
  return result;
}

/**
 * Cancel a subscription and free its seat (the backend reuses the shared
 * cancel path, so this mirrors suspending a member).
 */
export async function cancelSubscription(
  subscriptionId: string,
): Promise<ActionResult> {
  const result = await authedMutate(
    `/workspace/subscriptions/${subscriptionId}/cancel`,
    { method: "PUT" },
  );
  if (result.ok) {
    revalidateOwner("subscriptions");
    revalidateOwner("members");
    revalidateOwner("seats");
    revalidateOwner("");
  }
  return result;
}

/** The credentials + delivery result returned by provisioning internet access. */
export interface InternetCredentials {
  username: string;
  password: string;
  sent_email: boolean;
  sent_sms: boolean;
}

/**
 * Issue (or regenerate) the member's internet credentials and send them by
 * email + SMS. Returns the credentials so the owner can also share them directly.
 */
export async function provisionInternet(
  subscriptionId: string,
): Promise<ActionResult<InternetCredentials>> {
  const result = await authedMutate<InternetCredentials>(
    `/workspace/subscriptions/${subscriptionId}/internet`,
    { method: "POST" },
  );
  if (result.ok) revalidateOwner("subscriptions");
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Seats                                                                     */
/* -------------------------------------------------------------------------- */

export async function assignSeat(
  seatId: string,
  memberId: string,
): Promise<ActionResult<Seat>> {
  const result = await authedMutate<Seat>(`/seats/${seatId}/assign`, {
    method: "PUT",
    body: { member_id: memberId },
  });
  if (result.ok) {
    revalidateOwner("seats");
    revalidateOwner("");
  }
  return result;
}

export async function unassignSeat(seatId: string): Promise<ActionResult<Seat>> {
  const result = await authedMutate<Seat>(`/seats/${seatId}/unassign`, {
    method: "PUT",
  });
  if (result.ok) {
    revalidateOwner("seats");
    revalidateOwner("");
  }
  return result;
}

/** Rename a seat (its label/number). Unique within the workspace. */
export async function renameSeat(
  seatId: string,
  seatNumber: string,
): Promise<ActionResult<Seat>> {
  const result = await authedMutate<Seat>(`/seats/${seatId}`, {
    method: "PUT",
    body: { seat_number: seatNumber },
  });
  if (result.ok) {
    revalidateOwner("seats");
    revalidateOwner("");
  }
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Booking requests                                                          */
/* -------------------------------------------------------------------------- */

export interface ReviewBookingInput {
  action: "approve" | "reject";
  seatId?: string | null;
  /** Required when approving: the internet package to assign to the member. */
  packageId?: string | null;
  rejectionReason?: string | null;
}

export async function reviewBooking(
  bookingId: string,
  input: ReviewBookingInput,
): Promise<ActionResult> {
  const body: Record<string, unknown> = { action: input.action };
  if (input.action === "approve") {
    if (input.seatId) {
      body.seat_id = input.seatId;
    }
    if (input.packageId) {
      body.package_id = input.packageId;
    }
  }
  if (input.action === "reject" && input.rejectionReason) {
    body.rejection_reason = input.rejectionReason;
  }

  const result = await authedMutate(
    `/workspace/booking-requests/${bookingId}`,
    { method: "PUT", body },
  );
  if (result.ok) {
    revalidateOwner("bookings");
    revalidateOwner("requests");
    revalidateOwner("seats");
    revalidateOwner("");
  }
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Internet packages                                                         */
/* -------------------------------------------------------------------------- */

export interface PackageInput {
  name: string;
  speed_mbps: number;
  price: number;
  data_limit_gb?: number | null;
  is_unlimited: boolean;
  is_active?: boolean;
}

export async function createPackage(
  input: PackageInput,
): Promise<ActionResult<Package>> {
  const result = await authedMutate<Package>("/workspace/packages", {
    method: "POST",
    body: input,
  });
  if (result.ok) revalidateOwner("packages");
  return result;
}

export async function updatePackage(
  packageId: string,
  input: Partial<PackageInput>,
): Promise<ActionResult<Package>> {
  const result = await authedMutate<Package>(
    `/workspace/packages/${packageId}`,
    { method: "PUT", body: input },
  );
  if (result.ok) revalidateOwner("packages");
  return result;
}

export async function deletePackage(
  packageId: string,
): Promise<ActionResult> {
  const result = await authedMutate(`/workspace/packages/${packageId}`, {
    method: "DELETE",
  });
  if (result.ok) revalidateOwner("packages");
  return result;
}

export async function assignPackage(
  packageId: string,
  memberId: string,
): Promise<ActionResult<Package>> {
  const result = await authedMutate<Package>(
    `/workspace/packages/${packageId}/assign`,
    { method: "PUT", body: { member_id: memberId } },
  );
  if (result.ok) revalidateOwner("packages");
  return result;
}

export async function unassignPackage(
  packageId: string,
  memberId: string,
): Promise<ActionResult<Package>> {
  const result = await authedMutate<Package>(
    `/workspace/packages/${packageId}/unassign`,
    { method: "PUT", body: { member_id: memberId } },
  );
  if (result.ok) revalidateOwner("packages");
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Settings + photos                                                         */
/* -------------------------------------------------------------------------- */

/**
 * Settings update payload. total_seats and price_per_month are intentionally
 * absent: they are derived from the seat types (the single source of truth) and
 * recomputed by the backend whenever seat types are saved.
 */
export interface WorkspaceSettingsInput {
  name?: string;
  description?: string | null;
  address?: string;
  /** Id of an existing {@link City}; the backend resolves the display name. */
  city_id?: string;
  phone?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  amenities?: string[];
  working_hours?: Record<string, unknown> | null;
}

export async function updateSettings(
  input: WorkspaceSettingsInput,
): Promise<ActionResult<Workspace>> {
  const result = await authedMutate<Workspace>("/workspace/settings", {
    method: "PUT",
    body: input,
  });
  if (result.ok) {
    revalidateOwner("settings");
    revalidateOwner("");
  }
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Seat types & pricing                                                      */
/* -------------------------------------------------------------------------- */

/** A single per-seat-type pricing row submitted to `PUT /workspace/seat-types`. */
export interface SeatTypeInput {
  type: SeatType;
  price_monthly: number | null;
  price_daily: number | null;
  capacity: number;
  enabled: boolean;
}

/**
 * Upsert the owner's per-seat-type pricing. The backend returns the refreshed
 * workspace (all rows), so the page can re-render from authoritative data.
 */
export async function updateSeatTypes(
  rows: SeatTypeInput[],
): Promise<ActionResult<Workspace>> {
  const result = await authedMutate<Workspace>("/workspace/seat-types", {
    method: "PUT",
    body: { seat_types: rows },
  });
  if (result.ok) {
    revalidateOwner("settings");
    revalidateOwner("");
  }
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Messaging                                                                  */
/* -------------------------------------------------------------------------- */

/**
 * Set whether the workspace inherits Taqat's platform accounts and/or supply
 * its own SMTP + SMS config (`PUT /workspace/messaging`).
 *
 * Secrets are write-only: a blank/omitted password or SMS credential preserves
 * the stored value. The backend returns the refreshed masked messaging block.
 */
export async function updateWorkspaceMessaging(
  input: WorkspaceMessagingUpdateInput,
): Promise<ActionResult<WorkspaceMessagingConfig>> {
  const result = await authedMutate<WorkspaceMessagingConfig>(
    "/workspace/messaging",
    { method: "PUT", body: input },
  );
  if (result.ok) {
    revalidateOwner("settings");
    revalidateOwner("");
  }
  return result;
}

/**
 * Compose and broadcast an email and/or SMS to THIS workspace's members
 * (`POST /workspace/messaging/broadcast`). Recipients are scoped to the owner's
 * workspace server-side; the send is queued and the result reports the
 * queued/skipped counts per channel.
 */
export async function sendOwnerBroadcast(
  input: BroadcastInput,
): Promise<ActionResult<BroadcastResult>> {
  return authedMutate<BroadcastResult>("/workspace/messaging/broadcast", {
    method: "POST",
    body: input,
  });
}

export async function uploadPhotos(
  formData: FormData,
): Promise<ActionResult<Workspace>> {
  const result = await authedMutate<Workspace>("/workspace/photos", {
    method: "POST",
    formData,
  });
  if (result.ok) revalidateOwner("settings");
  return result;
}

export async function deletePhoto(
  path: string,
): Promise<ActionResult<Workspace>> {
  const result = await authedMutate<Workspace>("/workspace/photos", {
    method: "DELETE",
    body: { path },
  });
  if (result.ok) revalidateOwner("settings");
  return result;
}

export async function uploadLogo(
  formData: FormData,
): Promise<ActionResult<Workspace>> {
  const result = await authedMutate<Workspace>("/workspace/logo", {
    method: "POST",
    formData,
  });
  if (result.ok) revalidateOwner("settings");
  return result;
}

export async function deleteLogo(): Promise<ActionResult<Workspace>> {
  const result = await authedMutate<Workspace>("/workspace/logo", {
    method: "DELETE",
  });
  if (result.ok) revalidateOwner("settings");
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Management — Expenses                                                      */
/* -------------------------------------------------------------------------- */

export interface ExpenseInput {
  title: string;
  category: ExpenseCategory;
  amount: number;
  spent_on: string;
  notes?: string | null;
}

export async function createExpense(
  input: ExpenseInput,
): Promise<ActionResult<Expense>> {
  const result = await authedMutate<Expense>("/workspace/expenses", {
    method: "POST",
    body: input,
  });
  if (result.ok) revalidateOwner("expenses");
  return result;
}

export async function updateExpense(
  expenseId: string,
  input: Partial<ExpenseInput>,
): Promise<ActionResult<Expense>> {
  const result = await authedMutate<Expense>(
    `/workspace/expenses/${expenseId}`,
    { method: "PUT", body: input },
  );
  if (result.ok) revalidateOwner("expenses");
  return result;
}

export async function deleteExpense(
  expenseId: string,
): Promise<ActionResult> {
  const result = await authedMutate(`/workspace/expenses/${expenseId}`, {
    method: "DELETE",
  });
  if (result.ok) revalidateOwner("expenses");
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Management — Resources                                                     */
/* -------------------------------------------------------------------------- */

export interface ResourceInput {
  name: string;
  type: ResourceType;
  quantity: number;
  status: ResourceStatus;
  notes?: string | null;
}

export async function createResource(
  input: ResourceInput,
): Promise<ActionResult<Resource>> {
  const result = await authedMutate<Resource>("/workspace/resources", {
    method: "POST",
    body: input,
  });
  if (result.ok) revalidateOwner("resources");
  return result;
}

export async function updateResource(
  resourceId: string,
  input: Partial<ResourceInput>,
): Promise<ActionResult<Resource>> {
  const result = await authedMutate<Resource>(
    `/workspace/resources/${resourceId}`,
    { method: "PUT", body: input },
  );
  if (result.ok) revalidateOwner("resources");
  return result;
}

export async function deleteResource(
  resourceId: string,
): Promise<ActionResult> {
  const result = await authedMutate(`/workspace/resources/${resourceId}`, {
    method: "DELETE",
  });
  if (result.ok) revalidateOwner("resources");
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Cashiers (POS / café staff)                                               */
/* -------------------------------------------------------------------------- */

/** Email a cashier invitation for the owner's workspace. */
export async function inviteCashier(
  input: InviteCashierInput,
): Promise<ActionResult<CashierInvitation>> {
  const result = await authedMutate<CashierInvitation>(
    "/workspace/cashiers/invite",
    { method: "POST", body: input },
  );
  if (result.ok) revalidateOwner("cashiers");
  return result;
}

/** Re-scope a cashier's POS permission grant. */
export async function updateCashierPermissions(
  cashierId: string,
  permissions: PosPermission[],
): Promise<ActionResult<Cashier>> {
  const result = await authedMutate<Cashier>(
    `/workspace/cashiers/${cashierId}/permissions`,
    { method: "PUT", body: { permissions } },
  );
  if (result.ok) revalidateOwner("cashiers");
  return result;
}

/** Deactivate (suspend) a cashier account. */
export async function deactivateCashier(
  cashierId: string,
): Promise<ActionResult> {
  const result = await authedMutate(`/workspace/cashiers/${cashierId}`, {
    method: "DELETE",
  });
  if (result.ok) revalidateOwner("cashiers");
  return result;
}

/** Permanently delete a cashier account (history is preserved with a null actor). */
export async function deleteCashierAccount(
  cashierId: string,
): Promise<ActionResult> {
  const result = await authedMutate(
    `/workspace/cashiers/${cashierId}/permanent`,
    { method: "DELETE" },
  );
  if (result.ok) revalidateOwner("cashiers");
  return result;
}

/** Re-send a pending cashier invitation email (refreshes its expiry). */
export async function resendCashierInvitation(
  invitationId: string,
): Promise<ActionResult<CashierInvitation>> {
  const result = await authedMutate<CashierInvitation>(
    `/workspace/cashiers/invitations/${invitationId}/resend`,
    { method: "POST" },
  );
  if (result.ok) revalidateOwner("cashiers");
  return result;
}

/** Revoke (delete) a pending cashier invitation. */
export async function deleteCashierInvitation(
  invitationId: string,
): Promise<ActionResult> {
  const result = await authedMutate(
    `/workspace/cashiers/invitations/${invitationId}`,
    { method: "DELETE" },
  );
  if (result.ok) revalidateOwner("cashiers");
  return result;
}
