import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { OperationsTab } from "./OperationsTab";
import type { Workspace } from "@/lib/types";
import type { ApprovalRequestEntry } from "@/lib/types/approvals";

const toast = vi.fn();
const submitOperationsSettings = vi.fn();
const cancelApprovalRequest = vi.fn();

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));
vi.mock("@/lib/actions/owner", () => ({
  submitOperationsSettings: (...args: unknown[]) => submitOperationsSettings(...args),
  cancelApprovalRequest: (...args: unknown[]) => cancelApprovalRequest(...args),
}));

const workspace = {
  id: "w1",
  working_hours: null,
  seating_mode: "flexible",
  tax_rate: "5.00",
  seat_types: [
    { type: "flexible", enabled: true, capacity: 10, price_monthly: "100.00", price_daily: null },
  ],
} as unknown as Workspace;

const pendingRequest = {
  id: "r1",
  type: "capacity",
  status: "pending",
  change: {
    current: { seat_types: [{ type: "flexible", capacity: 10, enabled: true }] },
    proposed: { seat_types: [{ type: "flexible", capacity: 25, enabled: true }] },
  },
} as unknown as ApprovalRequestEntry;

describe("OperationsTab", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    submitOperationsSettings.mockResolvedValue({ ok: true, data: { requests: [{}] } });
    cancelApprovalRequest.mockResolvedValue({ ok: true, data: {} });
  });

  it("submits the form for approval with the edited capacity", async () => {
    const user = userEvent.setup();
    render(<OperationsTab workspace={workspace} pending={[]} />);

    const capacity = screen.getByDisplayValue("10");
    await user.clear(capacity);
    await user.type(capacity, "30");
    await user.click(screen.getByRole("button", { name: "operations.submit" }));

    await waitFor(() => expect(submitOperationsSettings).toHaveBeenCalled());
    const sent = submitOperationsSettings.mock.calls[0][0];
    expect(sent.tax_rate).toBe(5);
    expect(sent.seating_mode).toBe("flexible");
    expect(sent.seat_types[0]).toMatchObject({ type: "flexible", capacity: 30, price_monthly: 100 });
  });

  it("shows server validation errors for the tax rate", async () => {
    submitOperationsSettings.mockResolvedValue({ ok: false, message: "x", errors: { tax_rate: ["too high"] } });
    const user = userEvent.setup();
    render(<OperationsTab workspace={workspace} pending={[]} />);
    await user.click(screen.getByRole("button", { name: "operations.submit" }));

    expect(await screen.findByText("too high")).toBeInTheDocument();
  });

  it("lists a pending request with current and proposed values and can cancel it", async () => {
    const user = userEvent.setup();
    render(<OperationsTab workspace={workspace} pending={[pendingRequest]} />);

    expect(screen.getByText("operations.pendingBadge")).toBeInTheDocument();
    expect(screen.getByText(/10 → 25/)).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "operations.cancelRequest" }));
    await waitFor(() => expect(cancelApprovalRequest).toHaveBeenCalledWith("r1"));
  });
});
