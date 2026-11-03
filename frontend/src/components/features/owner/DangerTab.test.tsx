import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { DangerTab } from "./DangerTab";

const toast = vi.fn();
const confirm = vi.fn();
const resetWorkspace = vi.fn();

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));
vi.mock("@/components/ui/ConfirmDialog", () => ({
  useConfirm: () => confirm,
}));
vi.mock("@/lib/actions/owner", () => ({
  resetWorkspace: (...args: unknown[]) => resetWorkspace(...args),
}));

describe("DangerTab", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    confirm.mockResolvedValue(true);
    resetWorkspace.mockResolvedValue({ ok: true, data: undefined });
  });

  it("does not open the second step when the warning is dismissed", async () => {
    confirm.mockResolvedValue(false);
    const user = userEvent.setup();
    render(<DangerTab slug="acme" />);

    await user.click(screen.getByRole("button", { name: "reset" }));

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("only enables the final confirm once the slug and reason are typed", async () => {
    const user = userEvent.setup();
    render(<DangerTab slug="acme" />);
    await user.click(screen.getByRole("button", { name: "reset" }));

    const confirmButton = screen.getAllByRole("button", { name: "confirm" })[0];
    expect(confirmButton).toBeDisabled();

    // DOM order: slug, reason, authenticator code.
    const [slugInput, reasonInput, codeInput] = screen.getAllByRole("textbox");
    await user.type(slugInput, "acme");
    await user.type(reasonInput, "New season");
    await user.type(codeInput, "123456");

    expect(confirmButton).toBeEnabled();
    await user.click(confirmButton);

    await waitFor(() =>
      expect(resetWorkspace).toHaveBeenCalledWith({
        confirmation: "acme",
        reason: "New season",
        code: "123456",
      }),
    );
  });
});
