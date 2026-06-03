import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, Seat, SeatStatus, SeatType } from "@/lib/types";

/**
 * Seat map for a workspace (public; assignee names only for owner/admin).
 * The endpoint returns `{ seats, summary }`; callers want the seat array.
 */
export async function seatsForWorkspace(workspaceId: string): Promise<Seat[]> {
  const res = await serverFetch<ApiEnvelope<{ seats: Seat[]; summary?: unknown }>>(
    `/workspaces/${workspaceId}/seats`,
    { auth: false },
  );
  return res.data.seats;
}

export interface StoreSeatInput {
  seat_number: string;
  type: SeatType;
  status?: SeatStatus;
  notes?: string | null;
}

/** Owner: create a seat in their workspace. */
export async function storeSeat(input: StoreSeatInput): Promise<Seat> {
  const res = await serverFetch<ApiEnvelope<Seat>>("/workspace/seats", {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data;
}

export interface UpdateSeatInput {
  type?: SeatType;
  status?: SeatStatus;
  notes?: string | null;
}

/** Owner: update an existing seat. */
export async function updateSeat(
  seatId: string,
  input: UpdateSeatInput,
): Promise<Seat> {
  const res = await serverFetch<ApiEnvelope<Seat>>(`/seats/${seatId}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
  return res.data;
}

/** Owner: assign a seat to a member. */
export async function assignSeat(
  seatId: string,
  memberId: string,
): Promise<Seat> {
  const res = await serverFetch<ApiEnvelope<Seat>>(`/seats/${seatId}/assign`, {
    method: "PUT",
    body: JSON.stringify({ member_id: memberId }),
  });
  return res.data;
}

/** Owner: free a seat. */
export async function unassignSeat(seatId: string): Promise<Seat> {
  const res = await serverFetch<ApiEnvelope<Seat>>(
    `/seats/${seatId}/unassign`,
    { method: "PUT" },
  );
  return res.data;
}

/** Owner: delete a seat. */
export async function destroySeat(seatId: string): Promise<void> {
  await serverFetch<void>(`/seats/${seatId}`, { method: "DELETE" });
}
