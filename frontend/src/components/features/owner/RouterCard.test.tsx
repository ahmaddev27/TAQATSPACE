import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { RouterCard } from "./RouterCard";
import type { OwnerRouter } from "@/lib/types/router";

const toast = vi.fn();
const confirm = vi.fn();
const rotateRouterKey = vi.fn();
const unlockRouter = vi.fn();
const updateRouterScript = vi.fn();

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));
vi.mock("@/components/ui/ConfirmDialog", () => ({
  useConfirm: () => confirm,
}));
vi.mock("@/lib/actions/owner", () => ({
  rotateRouterKey: (...args: unknown[]) => rotateRouterKey(...args),
  unlockRouter: (...args: unknown[]) => unlockRouter(...args),
  updateRouterScript: (...args: unknown[]) => updateRouterScript(...args),
}));

const router: OwnerRouter = {
  id: "r1",
  name: "Front desk",
  status: "online",
  last_seen_at: "2026-11-03T12:00:00Z",
  serial_number: "SN-123",
  routeros_version: "7.14.3",
  script_version: "1.0.0",
  mode: "full",
  workspace_code: "ABCD2345",
  endpoint: "https://sync.taqat.space/api/v1/hotspot/ABCD2345/sync",
  commands: [],
  script_update: { available: false, current_version: "1.0.0", latest_version: "1.0.0", mode: "full" },
};

describe("RouterCard", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    confirm.mockResolvedValue(true);
  });

  it("shows the status, code, endpoint and serial number", () => {
    render(<RouterCard router={router} />);

    expect(screen.getAllByText("status.online").length).toBeGreaterThan(0);
    expect(screen.getByText("ABCD2345")).toBeInTheDocument();
    expect(screen.getByText(router.endpoint)).toBeInTheDocument();
    expect(screen.getByText("SN-123")).toBeInTheDocument();
  });

  it("shows the offline state", () => {
    render(<RouterCard router={{ ...router, status: "offline" }} />);

    expect(screen.getAllByText("status.offline").length).toBeGreaterThan(0);
  });

  it("offers unlock only for a locked router and update only when one is available", () => {
    const { rerender } = render(<RouterCard router={router} />);
    expect(screen.queryByRole("button", { name: "unlock.action" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "update.action" })).not.toBeInTheDocument();

    rerender(
      <RouterCard
        router={{
          ...router,
          status: "locked",
          script_update: { available: true, current_version: "0.9.0", latest_version: "1.0.0", mode: "full" },
        }}
      />,
    );
    expect(screen.getByRole("button", { name: "unlock.action" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "update.action" })).toBeInTheDocument();
  });

  it("shows the rotated key once and drops it when the dialog closes", async () => {
    rotateRouterKey.mockResolvedValue({ ok: true, data: { id: "r1", key: "secret-key-123", previous_key_expires_at: null } });
    const user = userEvent.setup();
    render(<RouterCard router={router} />);

    await user.click(screen.getByRole("button", { name: "rotate.action" }));
    await user.type(screen.getByRole("textbox"), "123456");
    await user.click(screen.getByRole("button", { name: "rotate.confirm" }));

    await waitFor(() => expect(screen.getByTestId("new-key")).toHaveTextContent("secret-key-123"));
    expect(rotateRouterKey).toHaveBeenCalledWith("r1", "123456");

    await user.click(screen.getByRole("button", { name: "rotate.done" }));
    expect(screen.queryByText("secret-key-123")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "rotate.action" }));
    expect(screen.queryByTestId("new-key")).not.toBeInTheDocument();
  });

  it("surfaces a failed 2FA check without showing a key", async () => {
    rotateRouterKey.mockResolvedValue({ ok: false, message: "bad code", status: 422, errors: { code: ["bad code"] } });
    const user = userEvent.setup();
    render(<RouterCard router={router} />);

    await user.click(screen.getByRole("button", { name: "rotate.action" }));
    await user.type(screen.getByRole("textbox"), "000000");
    await user.click(screen.getByRole("button", { name: "rotate.confirm" }));

    await waitFor(() => expect(screen.getByText("bad code")).toBeInTheDocument());
    expect(screen.queryByTestId("new-key")).not.toBeInTheDocument();
  });
});
