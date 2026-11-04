import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { BulkAddSeatsModal } from "./BulkAddSeatsModal";
import { bulkAddSeats } from "@/lib/actions/owner";
import type { Branch } from "@/lib/types";

vi.mock("@/lib/actions/owner", () => ({
  bulkAddSeats: vi.fn(),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

const branches: Branch[] = [
  { id: "b1", name: "Main", address: null, is_default: true },
];

describe("BulkAddSeatsModal", () => {
  beforeEach(() => {
    vi.mocked(bulkAddSeats).mockReset();
  });

  it("submits the entered range and closes on success", async () => {
    vi.mocked(bulkAddSeats).mockResolvedValue({ ok: true, data: { created: 80 } });
    const onClose = vi.fn();
    const user = userEvent.setup();
    render(<BulkAddSeatsModal branches={branches} onClose={onClose} />);

    await user.type(screen.getByLabelText("seats.stations.prefix"), "D-");
    await user.clear(screen.getByLabelText("seats.stations.count"));
    await user.type(screen.getByLabelText("seats.stations.count"), "80");
    await user.click(screen.getByRole("button", { name: "seats.stations.submit" }));

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(bulkAddSeats).toHaveBeenCalledWith(
      expect.objectContaining({ prefix: "D-", count: 80, start: 1, type: "flexible" }),
    );
  });

  it("disables submit above the 200 limit", async () => {
    const user = userEvent.setup();
    render(<BulkAddSeatsModal branches={branches} onClose={vi.fn()} />);

    await user.clear(screen.getByLabelText("seats.stations.count"));
    await user.type(screen.getByLabelText("seats.stations.count"), "201");

    expect(screen.getByRole("button", { name: "seats.stations.submit" })).toBeDisabled();
  });

  it("shows the server message when a number already exists", async () => {
    vi.mocked(bulkAddSeats).mockResolvedValue({
      ok: false,
      message: "Nothing was created: D-1",
      status: 422,
    });
    const user = userEvent.setup();
    render(<BulkAddSeatsModal branches={branches} onClose={vi.fn()} />);

    await user.click(screen.getByRole("button", { name: "seats.stations.submit" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Nothing was created: D-1");
  });
});
