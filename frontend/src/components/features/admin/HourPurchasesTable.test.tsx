import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

const cancelHourPurchase = vi.fn().mockResolvedValue({ ok: true });
const activateHourPurchase = vi.fn().mockResolvedValue({ ok: true });
const adjustHourBalance = vi.fn().mockResolvedValue({ ok: true });
const confirmFn = vi.fn().mockResolvedValue(true);
const toastFn = vi.fn();

vi.mock("@/lib/actions/hourPackages", () => ({
  cancelHourPurchase: (...a: unknown[]) => cancelHourPurchase(...a),
  activateHourPurchase: (...a: unknown[]) => activateHourPurchase(...a),
  adjustHourBalance: (...a: unknown[]) => adjustHourBalance(...a),
}));
vi.mock("@/components/ui/ConfirmDialog", () => ({ useConfirm: () => confirmFn }));
vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: toastFn }),
}));

import { HourPurchasesTable } from "./HourPurchasesTable";
import type { HourPurchase } from "@/lib/api/hourPackages";

const purchase: HourPurchase = {
  id: "p1",
  status: "requested",
  hours: 8,
  price: "10.00",
  currency: "USD",
  payment_method: null,
  receipt_url: null,
  paid_at: null,
  activated_at: null,
  expires_at: null,
  created_at: "2026-11-03T10:00:00Z",
  user: { id: "u1", name: "Sara", email: "sara@example.com" },
};
const stats = { requested: 1, active: 0, exhausted: 0, cancelled: 0, expired: 0 };

describe("HourPurchasesTable", () => {
  beforeEach(() => {
    cancelHourPurchase.mockClear().mockResolvedValue({ ok: true });
    activateHourPurchase.mockClear();
    adjustHourBalance.mockClear();
    confirmFn.mockClear().mockResolvedValue(true);
    toastFn.mockClear();
  });

  it("cancels a requested purchase after confirmation", async () => {
    const user = userEvent.setup();
    render(<HourPurchasesTable purchases={[purchase]} stats={stats} />);

    await user.click(screen.getByRole("button", { name: /cancel$/ }));

    expect(confirmFn).toHaveBeenCalledTimes(1);
    await waitFor(() => expect(cancelHourPurchase).toHaveBeenCalledWith("p1"));
  });

  it("does not cancel when the confirmation is declined", async () => {
    confirmFn.mockResolvedValue(false);
    const user = userEvent.setup();
    render(<HourPurchasesTable purchases={[purchase]} stats={stats} />);

    await user.click(screen.getByRole("button", { name: /cancel$/ }));

    expect(cancelHourPurchase).not.toHaveBeenCalled();
  });

  it("keeps activation disabled until a payment date and receipt are given", async () => {
    const user = userEvent.setup();
    render(<HourPurchasesTable purchases={[purchase]} stats={stats} />);

    await user.click(screen.getByRole("button", { name: /^activate$/ }));

    const confirmButton = screen.getByRole("button", {
      name: /activateDialog.confirm/,
    });
    expect(confirmButton).toBeDisabled();
    expect(activateHourPurchase).not.toHaveBeenCalled();
  });

  it("requires a reason before an adjustment can be applied", async () => {
    const user = userEvent.setup();
    render(<HourPurchasesTable purchases={[purchase]} stats={stats} />);

    await user.click(screen.getByRole("button", { name: /adjust$/ }));

    expect(
      screen.getByRole("button", { name: /adjustDialog.confirm/ }),
    ).toBeDisabled();
  });
});
