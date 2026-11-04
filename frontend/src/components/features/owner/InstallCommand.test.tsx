import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { act, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { InstallCommand } from "./InstallCommand";

const toast = vi.fn();
const generateInstallCommand = vi.fn();

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));
vi.mock("@/lib/actions/owner", () => ({
  generateInstallCommand: (...args: unknown[]) => generateInstallCommand(...args),
}));

const issue = (minutes: number, command = "/tool fetch url=x") => ({
  ok: true,
  data: { command, url: "u", expires_at: new Date(Date.now() + minutes * 60_000).toISOString() },
});

describe("InstallCommand", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("shows nothing until a command is generated", () => {
    render(<InstallCommand routerId="r1" />);

    expect(screen.queryByTestId("install-command")).not.toBeInTheDocument();
  });

  it("shows the command with a countdown, and a new one replaces it", async () => {
    generateInstallCommand.mockResolvedValueOnce(issue(30, "first")).mockResolvedValueOnce(issue(30, "second"));
    const user = userEvent.setup();
    render(<InstallCommand routerId="r1" />);

    await user.click(screen.getByRole("button", { name: "generate" }));
    await waitFor(() => expect(screen.getByTestId("install-command")).toHaveTextContent("first"));
    expect(screen.getByRole("timer")).toHaveTextContent("expiresIn");

    await user.click(screen.getByRole("button", { name: "regenerate" }));
    await waitFor(() => expect(screen.getByTestId("install-command")).toHaveTextContent("second"));
    expect(generateInstallCommand).toHaveBeenCalledWith("r1");
  });

  it("marks the command expired when the countdown ends", async () => {
    generateInstallCommand.mockResolvedValue(issue(0.02));
    const user = userEvent.setup();
    render(<InstallCommand routerId="r1" />);

    await user.click(screen.getByRole("button", { name: "generate" }));
    await waitFor(() => expect(screen.getByTestId("install-command")).toBeInTheDocument());

    await waitFor(() => expect(screen.getByRole("timer")).toHaveTextContent("expired"), { timeout: 4000 });
    expect(screen.getByRole("button", { name: "copy" })).toBeDisabled();
  });

  it("does not persist the command in browser storage", async () => {
    generateInstallCommand.mockResolvedValue(issue(30, "secret-cmd"));
    const user = userEvent.setup();
    render(<InstallCommand routerId="r1" />);

    await user.click(screen.getByRole("button", { name: "generate" }));
    await act(async () => {});

    expect(JSON.stringify({ ...localStorage })).not.toContain("secret-cmd");
    expect(JSON.stringify({ ...sessionStorage })).not.toContain("secret-cmd");
  });

  it("reports a failure without showing a command", async () => {
    generateInstallCommand.mockResolvedValue({ ok: false, message: "nope", status: 500 });
    const user = userEvent.setup();
    render(<InstallCommand routerId="r1" />);

    await user.click(screen.getByRole("button", { name: "generate" }));

    await waitFor(() => expect(toast).toHaveBeenCalled());
    expect(screen.queryByTestId("install-command")).not.toBeInTheDocument();
  });
});
