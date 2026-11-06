import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { RecalculateCard } from "./RecalculateCard";
import type { RecalculationStatus } from "@/lib/types/tools";

const toast = vi.fn();
const confirm = vi.fn();
const getRecalculationStatus = vi.fn();
const startRecalculation = vi.fn();

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));
vi.mock("@/components/ui/ConfirmDialog", () => ({
  useConfirm: () => confirm,
}));
vi.mock("@/lib/actions/owner", () => ({
  getRecalculationStatus: (...args: unknown[]) => getRecalculationStatus(...args),
  startRecalculation: (...args: unknown[]) => startRecalculation(...args),
}));

function status(state: RecalculationStatus["state"], extra: Partial<RecalculationStatus> = {}): RecalculationStatus {
  return {
    state,
    active: state === "queued" || state === "running",
    from: "2026-11-01",
    to: "2026-11-06",
    requested_at: "2026-11-06T10:00:00+00:00",
    finished_at: null,
    written: null,
    ...extra,
  };
}

function fillRange(container: HTMLElement) {
  const [from, to] = Array.from(container.querySelectorAll<HTMLInputElement>('input[type="date"]'));
  fireEvent.change(from, { target: { value: "2026-11-01" } });
  fireEvent.change(to, { target: { value: "2026-11-06" } });
}

describe("RecalculateCard", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    confirm.mockResolvedValue(true);
    getRecalculationStatus.mockResolvedValue({ ok: true, data: status("idle", { from: null, to: null, requested_at: null }) });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("shows the latest status when it opens", async () => {
    getRecalculationStatus.mockResolvedValue({
      ok: true,
      data: status("completed", { written: { days: 6, subscriber_months: 2 }, finished_at: "2026-11-06T10:01:00+00:00" }),
    });
    render(<RecalculateCard />);

    expect(await screen.findByTestId("recalculate-state")).toHaveAttribute("data-state", "completed");
    expect(screen.getByTestId("recalculate-summary")).toBeInTheDocument();
  });

  it("says so when the figures were written but the scoring failed", async () => {
    getRecalculationStatus.mockResolvedValue({
      ok: true,
      data: status("completed", { written: { days: 6, subscriber_months: 2 }, scoring_failed: true }),
    });
    render(<RecalculateCard />);

    expect(await screen.findByTestId("recalculate-summary")).toHaveTextContent("scoringFailed");
  });

  it("keeps the button disabled until both dates are chosen", async () => {
    const { container } = render(<RecalculateCard />);
    const button = screen.getByRole("button", { name: "recalculate" });
    expect(button).toBeDisabled();

    fillRange(container);

    expect(button).toBeEnabled();
  });

  it("asks for confirmation and starts the recalculation for the range", async () => {
    startRecalculation.mockResolvedValue({ ok: true, data: status("queued") });
    const { container } = render(<RecalculateCard />);
    fillRange(container);

    fireEvent.click(screen.getByRole("button", { name: "recalculate" }));

    await waitFor(() => expect(startRecalculation).toHaveBeenCalledWith({ from: "2026-11-01", to: "2026-11-06" }));
    expect(confirm).toHaveBeenCalledTimes(1);
    expect(await screen.findByTestId("recalculate-state")).toHaveAttribute("data-state", "queued");
  });

  it("does nothing when the confirmation is dismissed", async () => {
    confirm.mockResolvedValue(false);
    const { container } = render(<RecalculateCard />);
    fillRange(container);

    fireEvent.click(screen.getByRole("button", { name: "recalculate" }));

    await waitFor(() => expect(confirm).toHaveBeenCalled());
    expect(startRecalculation).not.toHaveBeenCalled();
  });

  it("shows field errors and reloads the status when another run is already going", async () => {
    startRecalculation.mockResolvedValue({ ok: false, status: 409, message: "A recalculation is already running." });
    const { container } = render(<RecalculateCard />);
    fillRange(container);
    getRecalculationStatus.mockResolvedValue({ ok: true, data: status("running") });

    fireEvent.click(screen.getByRole("button", { name: "recalculate" }));

    await waitFor(() => expect(toast).toHaveBeenCalledWith(expect.objectContaining({ tone: "err", body: "A recalculation is already running." })));
    expect(await screen.findByTestId("recalculate-state")).toHaveAttribute("data-state", "running");
  });

  it("polls while a run is active and stops once it completes", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    getRecalculationStatus
      .mockResolvedValueOnce({ ok: true, data: status("running") })
      .mockResolvedValue({ ok: true, data: status("completed", { written: { days: 6, subscriber_months: 1 } }) });
    render(<RecalculateCard />);
    expect(await screen.findByTestId("recalculate-state")).toHaveAttribute("data-state", "running");

    await act(async () => {
      await vi.advanceTimersByTimeAsync(3100);
    });

    expect(await screen.findByText("state.completed")).toBeInTheDocument();
    expect(toast).toHaveBeenCalledWith(expect.objectContaining({ tone: "ok", title: "finished" }));

    const calls = getRecalculationStatus.mock.calls.length;
    await act(async () => {
      await vi.advanceTimersByTimeAsync(10000);
    });
    expect(getRecalculationStatus.mock.calls.length).toBe(calls);
  });
});
