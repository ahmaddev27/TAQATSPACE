import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { InternetCredentialsCard } from "./InternetCredentialsCard";

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

describe("InternetCredentialsCard", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("always shows the username", () => {
    render(<InternetCredentialsCard username="alice" password="s3cret" />);
    expect(screen.getByText("alice")).toBeInTheDocument();
  });

  it("masks the password initially with bullets", () => {
    render(<InternetCredentialsCard username="alice" password="s3cret" />);
    expect(screen.getByText("••••••••")).toBeInTheDocument();
    expect(screen.queryByText("s3cret")).not.toBeInTheDocument();
  });

  it("reveals the real password when the reveal toggle is clicked", async () => {
    const user = userEvent.setup();
    render(<InternetCredentialsCard username="alice" password="s3cret" />);

    await user.click(screen.getByRole("button", { name: "reveal" }));

    expect(screen.getByText("s3cret")).toBeInTheDocument();
    expect(screen.queryByText("••••••••")).not.toBeInTheDocument();
  });

  it("copies the password via navigator.clipboard.writeText", async () => {
    // userEvent.setup() installs its own clipboard; spy on whatever is active.
    const user = userEvent.setup();
    const writeText = vi.spyOn(navigator.clipboard, "writeText");
    render(<InternetCredentialsCard username="alice" password="s3cret" />);

    // Two copy buttons (username + password); the password copy is the last.
    const copyButtons = screen.getAllByRole("button", { name: "copy" });
    await user.click(copyButtons[copyButtons.length - 1]);

    expect(writeText).toHaveBeenCalledWith("s3cret");
  });

  it("copies the username via navigator.clipboard.writeText", async () => {
    const user = userEvent.setup();
    const writeText = vi.spyOn(navigator.clipboard, "writeText");
    render(<InternetCredentialsCard username="alice" password="s3cret" />);

    const copyButtons = screen.getAllByRole("button", { name: "copy" });
    await user.click(copyButtons[0]);

    expect(writeText).toHaveBeenCalledWith("alice");
  });

  it("hides the password section when no password is provided", () => {
    render(<InternetCredentialsCard username="alice" password={null} />);
    expect(screen.queryByText("••••••••")).not.toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "reveal" }),
    ).not.toBeInTheDocument();
  });
});
