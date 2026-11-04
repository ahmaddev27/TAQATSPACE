import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

const voidInvoice = vi.fn();
const toastFn = vi.fn();

vi.mock("@/lib/actions/invoices", () => ({
  voidInvoice: (...args: unknown[]) => voidInvoice(...args),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: toastFn }),
}));

import { VoidInvoiceButton } from "./VoidInvoiceButton";

const lastButton = () => screen.getAllByRole("button").at(-1)!;

describe("VoidInvoiceButton", () => {
  beforeEach(() => {
    voidInvoice.mockReset().mockResolvedValue({ ok: true });
    toastFn.mockClear();
  });

  it("requires a reason before calling the action", async () => {
    const user = userEvent.setup();
    render(<VoidInvoiceButton invoiceId="inv-1" invoiceNumber="INV-1" />);

    await user.click(screen.getByRole("button"));
    await user.click(lastButton());

    expect(voidInvoice).not.toHaveBeenCalled();
  });

  it("voids with the typed reason", async () => {
    const user = userEvent.setup();
    render(<VoidInvoiceButton invoiceId="inv-1" invoiceNumber="INV-1" />);

    await user.click(screen.getByRole("button"));
    await user.type(screen.getByRole("textbox"), "Wrong customer");
    await user.click(lastButton());

    await waitFor(() => {
      expect(voidInvoice).toHaveBeenCalledWith("inv-1", "Wrong customer", undefined);
    });
    await waitFor(() => {
      expect(toastFn).toHaveBeenCalledWith(expect.objectContaining({ tone: "ok" }));
    });
  });

  it("asks for a two-factor code after a 403 and retries with it", async () => {
    voidInvoice.mockResolvedValueOnce({ ok: false, status: 403, message: "2fa" });
    const user = userEvent.setup();
    render(<VoidInvoiceButton invoiceId="inv-1" invoiceNumber="INV-1" />);

    await user.click(screen.getByRole("button"));
    await user.type(screen.getByRole("textbox"), "Duplicate");
    await user.click(lastButton());

    await waitFor(() => {
      expect(screen.getAllByRole("textbox")).toHaveLength(2);
    });
    await user.type(screen.getAllByRole("textbox")[1], "123456");
    await user.click(lastButton());

    await waitFor(() => {
      expect(voidInvoice).toHaveBeenLastCalledWith("inv-1", "Duplicate", "123456");
    });
  });
});
