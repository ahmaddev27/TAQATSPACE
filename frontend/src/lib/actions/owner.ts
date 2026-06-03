"use server";

import { revalidatePath } from "next/cache";
import { serverFetch } from "@/lib/api";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type {
  ApiEnvelope,
  Member,
  Package,
  Seat,
  SubscriptionStatus,
  Workspace,
} from "@/lib/types";

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

/* -------------------------------------------------------------------------- */
/*  Booking requests                                                          */
/* -------------------------------------------------------------------------- */

export interface ReviewBookingInput {
  action: "approve" | "reject";
  seatId?: string | null;
  rejectionReason?: string | null;
}

export async function reviewBooking(
  bookingId: string,
  input: ReviewBookingInput,
): Promise<ActionResult> {
  const body: Record<string, unknown> = { action: input.action };
  if (input.action === "approve" && input.seatId) {
    body.seat_id = input.seatId;
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

export interface WorkspaceSettingsInput {
  name?: string;
  description?: string | null;
  address?: string;
  city?: string;
  phone?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  total_seats?: number;
  price_per_month?: number;
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
