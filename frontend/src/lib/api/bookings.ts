import { serverFetch } from "@/lib/api";
import type {
  ApiEnvelope,
  BookingRequest,
  BookingStatus,
  Paginated,
  SeatType,
} from "@/lib/types";

export interface SubmitBookingInput {
  workspace_id: string;
  preferred_seat_type?: SeatType | null;
  message?: string | null;
}

/** Freelancer: submit a booking request. */
export async function submitBooking(
  input: SubmitBookingInput,
): Promise<BookingRequest> {
  const res = await serverFetch<ApiEnvelope<BookingRequest>>(
    "/booking-requests",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}

export interface ListBookingsParams {
  status?: BookingStatus;
  per_page?: number;
  page?: number;
}

/** Owner: paginated booking-request queue for their workspace. */
export async function listBookingsForOwner(
  params: ListBookingsParams = {},
): Promise<Paginated<BookingRequest>> {
  const search = new URLSearchParams();
  if (params.status) search.set("status", params.status);
  if (params.per_page) search.set("per_page", String(params.per_page));
  if (params.page) search.set("page", String(params.page));
  const qs = search.toString();

  const res = await serverFetch<ApiEnvelope<Paginated<BookingRequest>>>(
    `/workspace/booking-requests${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}

export interface ReviewBookingInput {
  action: "approve" | "reject";
  /** Required when approving — the seat to assign. */
  seat_id?: string | null;
  rejection_reason?: string | null;
}

/** Owner: approve or reject a booking request. */
export async function reviewBooking(
  bookingId: string,
  input: ReviewBookingInput,
): Promise<BookingRequest> {
  const res = await serverFetch<ApiEnvelope<BookingRequest>>(
    `/workspace/booking-requests/${bookingId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data;
}
