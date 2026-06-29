import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ProvisionInternetButton } from "./ProvisionInternetButton";

const provisionInternet = vi.fn();
vi.mock("@/lib/actions/owner", () => ({
  provisionInternet: (...args: unknown[]) => provisionInternet(...args),
}));

const toast = vi.fn();
vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));

const confirm = vi.fn();
vi.mock("@/components/ui/ConfirmDialog", () => ({
  useConfirm: () => confirm,
}));

describe("ProvisionInternetButton", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    provisionInternet.mockResolvedValue({
      ok: true,
      data: {
        username: "u1",
        password: "p1",
        sent_email: true,
        sent_sms: false,
      },
    });
    confirm.mockResolvedValue(true);
  });

  it("shows username and password in a modal after a successful create click", async () => {
    const user = userEvent.setup();
    render(
      <ProvisionInternetButton
        subscriptionId="sub-1"
        memberName="Sara"
        hasCredentials={false}
      />,
    );

    await user.click(screen.getByRole("button"));

    expect(provisionInternet).toHaveBeenCalledWith("sub-1");
    expect(await screen.findByText("u1")).toBeInTheDocument();
    expect(screen.getByText("p1")).toBeInTheDocument();
  });

  it("confirms before the regenerate path then shows the credentials", async () => {
    const user = userEvent.setup();
    render(
      <ProvisionInternetButton
        subscriptionId="sub-2"
        memberName="Omar"
        hasCredentials
      />,
    );

    await user.click(screen.getByRole("button"));

    expect(confirm).toHaveBeenCalled();
    expect(provisionInternet).toHaveBeenCalledWith("sub-2");
    expect(await screen.findByText("u1")).toBeInTheDocument();
  });
});
