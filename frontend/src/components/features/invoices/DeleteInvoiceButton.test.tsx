import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

const deleteInvoice = vi.fn().mockResolvedValue({ ok: true });
const confirmFn = vi.fn().mockResolvedValue(true);
const toastFn = vi.fn();

vi.mock("@/lib/actions/invoices", () => ({
  deleteInvoice: (...args: unknown[]) => deleteInvoice(...args),
}));

vi.mock("@/components/ui/ConfirmDialog", () => ({
  useConfirm: () => confirmFn,
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: toastFn }),
}));

import { DeleteInvoiceButton } from "./DeleteInvoiceButton";

describe("DeleteInvoiceButton", () => {
  beforeEach(() => {
    deleteInvoice.mockClear().mockResolvedValue({ ok: true });
    confirmFn.mockClear().mockResolvedValue(true);
    toastFn.mockClear();
  });

  it("confirms then calls deleteInvoice with the invoice id", async () => {
    const user = userEvent.setup();
    render(<DeleteInvoiceButton invoiceId="inv-99" invoiceNumber="INV-99" />);

    await user.click(screen.getByRole("button"));

    expect(confirmFn).toHaveBeenCalledTimes(1);
    await waitFor(() => {
      expect(deleteInvoice).toHaveBeenCalledWith("inv-99");
    });
    await waitFor(() => {
      expect(toastFn).toHaveBeenCalledWith(
        expect.objectContaining({ tone: "ok" }),
      );
    });
  });

  it("does not call deleteInvoice when the user cancels the confirm", async () => {
    confirmFn.mockResolvedValue(false);
    const user = userEvent.setup();
    render(<DeleteInvoiceButton invoiceId="inv-1" invoiceNumber="INV-1" />);

    await user.click(screen.getByRole("button"));

    expect(confirmFn).toHaveBeenCalledTimes(1);
    expect(deleteInvoice).not.toHaveBeenCalled();
    expect(toastFn).not.toHaveBeenCalled();
  });

  it("shows an error toast when deleteInvoice fails", async () => {
    deleteInvoice.mockResolvedValue({ ok: false, message: "boom" });
    const user = userEvent.setup();
    render(<DeleteInvoiceButton invoiceId="inv-2" invoiceNumber="INV-2" />);

    await user.click(screen.getByRole("button"));

    await waitFor(() => {
      expect(toastFn).toHaveBeenCalledWith(
        expect.objectContaining({ tone: "err", body: "boom" }),
      );
    });
  });
});
