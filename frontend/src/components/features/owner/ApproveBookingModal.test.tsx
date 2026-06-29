import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { BookingRequest, Package, Seat } from "@/lib/types";
import { BookingsManager } from "./BookingsManager";

const reviewBooking = vi.fn();
vi.mock("@/lib/actions/owner", () => ({
  reviewBooking: (...args: unknown[]) => reviewBooking(...args),
}));

const toast = vi.fn();
vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));

// The modal links to /owner/packages via the i18n-aware Link.
vi.mock("@/i18n/navigation", () => ({
  Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
}));

const booking: BookingRequest = {
  id: "bk-1",
  workspace_id: "ws-1",
  member_id: "m-1",
  preferred_seat_type: null,
  plan_type: "monthly",
  status: "pending",
  message: null,
  rejection_reason: null,
  reviewed_by: null,
  reviewed_at: null,
  created_at: "2026-01-01T00:00:00Z",
  member: { id: "m-1", name: "Sara", specialty: "Dev", avatar: null },
} as unknown as BookingRequest;

const seat: Seat = {
  id: "seat-1",
  seat_number: "A1",
  type: "flexible",
  status: "available",
} as unknown as Seat;

const pkg: Package = {
  id: "pkg-1",
  workspace_id: "ws-1",
  name: "Basic",
  speed_mbps: 50,
  price: "10.00",
  data_limit_gb: null,
  is_unlimited: true,
  is_active: true,
} as unknown as Package;

function openApproveModal() {
  return userEvent.setup();
}

describe("ApproveBookingModal (via BookingsManager)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    reviewBooking.mockResolvedValue({ ok: true });
  });

  it("disables approve until a package is selected, then calls reviewBooking with the package id", async () => {
    const user = openApproveModal();
    render(
      <BookingsManager
        bookings={[booking]}
        availableSeats={[seat]}
        packages={[pkg]}
      />,
    );

    // Open the approve modal from the booking row.
    await user.click(screen.getByText("bookings.approve"));

    const confirm = screen.getByText("bookings.confirmApprove")
      .closest("button") as HTMLButtonElement;
    expect(confirm).toBeDisabled();

    // Select the package -> approve becomes enabled.
    await user.selectOptions(screen.getByRole("combobox"), "pkg-1");
    expect(confirm).not.toBeDisabled();

    await user.click(confirm);
    expect(reviewBooking).toHaveBeenCalledWith("bk-1", {
      action: "approve",
      seatId: "seat-1",
      packageId: "pkg-1",
    });
  });

  it("blocks approval and hides the selector when there are no packages", async () => {
    const user = openApproveModal();
    render(
      <BookingsManager
        bookings={[booking]}
        availableSeats={[seat]}
        packages={[]}
      />,
    );

    await user.click(screen.getByText("bookings.approve"));

    expect(screen.getByText("bookings.noPackagesTitle")).toBeInTheDocument();
    expect(screen.queryByRole("combobox")).not.toBeInTheDocument();

    const confirm = screen.getByText("bookings.confirmApprove")
      .closest("button") as HTMLButtonElement;
    expect(confirm).toBeDisabled();
    expect(reviewBooking).not.toHaveBeenCalled();
  });
});
