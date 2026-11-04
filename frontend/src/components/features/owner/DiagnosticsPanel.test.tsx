import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { DiagnosticsPanel } from "./DiagnosticsPanel";

const toast = vi.fn();
const diagnoseRouter = vi.fn();

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));
vi.mock("@/lib/actions/owner", () => ({
  diagnoseRouter: (...args: unknown[]) => diagnoseRouter(...args),
}));

describe("DiagnosticsPanel", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("shows no results until the check is run", () => {
    render(<DiagnosticsPanel routerId="r1" />);

    expect(screen.queryByTestId("diagnostic-checks")).not.toBeInTheDocument();
  });

  it("renders a status and the failure reason for each check", async () => {
    diagnoseRouter.mockResolvedValueOnce({
      ok: true,
      data: {
        checks: [
          { name: "last_sync", status: "fail", detail: "The router has never synced with the platform." },
          { name: "clock_drift", status: "pass", detail: "Clock is fine." },
          { name: "serial", status: "warning", detail: "Not bound yet." },
        ],
      },
    });
    const user = userEvent.setup();
    render(<DiagnosticsPanel routerId="r1" />);

    await user.click(screen.getByRole("button", { name: "run" }));

    await waitFor(() => expect(screen.getByTestId("diagnostic-checks")).toBeInTheDocument());
    expect(diagnoseRouter).toHaveBeenCalledWith("r1");
    expect(screen.getAllByRole("listitem")).toHaveLength(3);
    expect(screen.getByText("The router has never synced with the platform.")).toBeInTheDocument();
    expect(screen.getByText("status.fail")).toBeInTheDocument();
    expect(screen.getByText("status.pass")).toBeInTheDocument();
    expect(screen.getByText("status.warning")).toBeInTheDocument();
    expect(screen.getByText("checks.last_sync")).toBeInTheDocument();
  });

  it("toasts the error when the check cannot run", async () => {
    diagnoseRouter.mockResolvedValueOnce({ ok: false, message: "Boom", status: 500 });
    const user = userEvent.setup();
    render(<DiagnosticsPanel routerId="r1" />);

    await user.click(screen.getByRole("button", { name: "run" }));

    await waitFor(() => expect(toast).toHaveBeenCalledWith({ tone: "err", title: "failed", body: "Boom" }));
    expect(screen.queryByTestId("diagnostic-checks")).not.toBeInTheDocument();
  });
});
