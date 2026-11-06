import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { WalletManager } from "./WalletManager";
import { forfeitWallet, voidPaymentVoucher } from "@/lib/actions/financeWallet";
import type { PaymentVoucher, WalletRow } from "@/lib/types/financeWallet";

vi.mock("@/lib/actions/financeWallet", () => ({
  receiveDeposit: vi.fn().mockResolvedValue({ ok: true }),
  returnDeposit: vi.fn().mockResolvedValue({ ok: true }),
  settleWallet: vi.fn().mockResolvedValue({ ok: true }),
  forfeitWallet: vi.fn().mockResolvedValue({ ok: true }),
  voidPaymentVoucher: vi.fn().mockResolvedValue({ ok: true }),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

const wallet: WalletRow = { customer: { id: "c1", name: "Lina" }, balance: "120.00", last_entry_at: "2026-02-01T10:00:00Z" };
const empty: WalletRow = { customer: { id: "c2", name: "Omar" }, balance: "0.00", last_entry_at: null };

const voucher: PaymentVoucher = {
  id: "v1",
  number: "WS-PV-000001",
  customer_id: "c1",
  customer: { id: "c1", name: "Lina" },
  method: "cash",
  amount: "50.00",
  reason: "Back",
  reference: null,
  wallet_entry_id: "e1",
  paid_at: "2026-02-02T10:00:00Z",
  voided_at: null,
  void_reason: null,
  created_at: null,
};

const customers = [{ id: "c1", name: "Lina" }];

describe("WalletManager", () => {
  beforeEach(() => vi.clearAllMocks());

  it("lists balances and the voucher with its PDF link", () => {
    render(<WalletManager wallets={[wallet]} vouchers={[voucher]} customers={customers} invoices={[]} />);

    expect(screen.getByText("₪120")).toBeInTheDocument();
    expect(screen.getByText("WS-PV-000001")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /downloadPdf/ })).toHaveAttribute("href", "/api/payment-vouchers/v1/pdf");
  });

  it("disables returning, paying and forfeiting on an empty wallet", () => {
    render(<WalletManager wallets={[empty]} vouchers={[]} customers={customers} invoices={[]} />);

    expect(screen.getByRole("button", { name: "return" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "settle" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "forfeit" })).toBeDisabled();
  });

  it("forfeits only once a reason is given", async () => {
    render(<WalletManager wallets={[wallet]} vouchers={[]} customers={customers} invoices={[]} />);

    await userEvent.click(screen.getByRole("button", { name: "forfeit" }));
    const confirm = screen.getByRole("button", { name: "save" });
    await userEvent.type(screen.getByLabelText(/fields.amount/), "10");
    expect(confirm).toBeDisabled();

    await userEvent.type(screen.getByLabelText(/fields.reason/), "Damaged desk");
    await userEvent.click(confirm);

    expect(forfeitWallet).toHaveBeenCalledWith("c1", { amount: "10", reason: "Damaged desk" }, undefined);
  });

  it("voids a voucher with the reason and the two-factor code", async () => {
    render(<WalletManager wallets={[]} vouchers={[voucher]} customers={customers} invoices={[]} />);

    await userEvent.click(screen.getByRole("button", { name: "void" }));
    await userEvent.type(screen.getByLabelText(/voidReason/), "Paid twice");
    await userEvent.type(screen.getByLabelText(/fields.twoFactor/), "123456");
    await userEvent.click(screen.getAllByRole("button", { name: "void" }).at(-1)!);

    expect(voidPaymentVoucher).toHaveBeenCalledWith("v1", "Paid twice", "123456");
  });
});
