import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { SeatBoard } from "./SeatBoard";
import type { Member, Seat } from "@/lib/types";

vi.mock("@/lib/actions/owner", () => ({
  assignSeat: vi.fn().mockResolvedValue({ ok: true }),
  unassignSeat: vi.fn().mockResolvedValue({ ok: true }),
  renameSeat: vi.fn().mockResolvedValue({ ok: true }),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

const seats: Seat[] = [
  {
    id: "seat-1",
    seat_number: "A1",
    type: "flexible",
    status: "available",
    assigned_member: null,
  } as Seat,
];

const members: Member[] = [];

describe("SeatBoard", () => {
  it("renders the rename input for a selected seat", async () => {
    const user = userEvent.setup();
    render(<SeatBoard seats={seats} members={members} />);

    // Select the seat by clicking its button (label is the seat number).
    await user.click(screen.getByRole("button", { name: /A1/ }));

    // SeatRename renders a textbox pre-filled with the seat number.
    const input = await screen.findByDisplayValue("A1");
    expect(input).toBeInTheDocument();
    expect(screen.getByText("seats.renameLabel")).toBeInTheDocument();
  });
});
