import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { SecuritySettings, type TwoFactorStatus } from "./SecuritySettings";

const enableAction = vi.fn();
const confirmAction = vi.fn();
const disableAction = vi.fn();

vi.mock("@/lib/actions/twoFactor", () => ({
  enableTwoFactorAction: () => enableAction(),
  confirmTwoFactorAction: (code: string) => confirmAction(code),
  disableTwoFactorAction: (input: unknown) => disableAction(input),
}));

const off: TwoFactorStatus = {
  eligible: true,
  enabled: false,
  pending_confirmation: false,
  recovery_codes_remaining: 0,
};

describe("SecuritySettings", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    enableAction.mockResolvedValue({
      ok: true,
      data: { secret: "ABCDEF", otpauth_url: "otpauth://totp/x", qr_svg: "<svg></svg>" },
    });
  });

  it("explains that freelancers cannot enable 2FA", () => {
    render(<SecuritySettings status={{ ...off, eligible: false }} />);
    expect(screen.getByText("notEligible")).toBeInTheDocument();
    expect(screen.queryByText("enable")).not.toBeInTheDocument();
  });

  it("walks through enrollment and shows recovery codes once", async () => {
    confirmAction.mockResolvedValue({
      ok: true,
      data: { recovery_codes: ["aaaaa-bbbbb", "ccccc-ddddd"] },
    });
    const user = userEvent.setup();
    render(<SecuritySettings status={off} />);

    await user.click(screen.getByRole("button", { name: "enable" }));
    expect(await screen.findByText("ABCDEF")).toBeInTheDocument();

    const confirm = screen.getByRole("button", { name: "confirm" });
    expect(confirm).toBeDisabled();

    await user.type(screen.getByRole("textbox"), "123456");
    await user.click(confirm);

    expect(confirmAction).toHaveBeenCalledWith("123456");
    expect(await screen.findByText("aaaaa-bbbbb")).toBeInTheDocument();
    expect(screen.getByText("ccccc-ddddd")).toBeInTheDocument();
  });

  it("shows an error and stays on the dialog when the code is wrong", async () => {
    confirmAction.mockResolvedValue({ ok: false, status: 422, message: "bad" });
    const user = userEvent.setup();
    render(<SecuritySettings status={off} />);

    await user.click(screen.getByRole("button", { name: "enable" }));
    await user.type(await screen.findByRole("textbox"), "000000");
    await user.click(screen.getByRole("button", { name: "confirm" }));

    expect(await screen.findByText("invalidCode")).toBeInTheDocument();
    expect(screen.queryByText("recoveryTitle")).not.toBeInTheDocument();
  });

  it("requires a code to disable when enabled", async () => {
    disableAction.mockResolvedValue({ ok: true, data: undefined });
    const user = userEvent.setup();
    render(<SecuritySettings status={{ ...off, enabled: true, recovery_codes_remaining: 7 }} />);

    expect(screen.getByText("statusOn")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "disable" }));

    const submit = screen
      .getAllByRole("button", { name: "disable" })
      .find((button) => button.closest(".modal"))!;
    expect(submit).toBeDisabled();

    await user.type(screen.getByRole("textbox"), "654321");
    await user.click(submit);

    await waitFor(() => expect(disableAction).toHaveBeenCalledWith({ code: "654321" }));
  });
});
