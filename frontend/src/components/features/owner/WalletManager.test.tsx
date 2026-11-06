import { describe, it, expect, vi, beforeEach } from "vitest";
import { renderToString } from "react-dom/server";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { WalletManager } from "./WalletManager";
import {
  forfeitWallet,
  loadWalletEntries,
  receiveDeposit,
  returnDeposit,
  reverseWalletEntry,
  settleWallet,
  voidPaymentVoucher,
} from "@/lib/actions/financeWallet";
import type { PaymentVoucher, WalletEntry, WalletRow } from "@/lib/types/financeWallet";

vi.mock("@/lib/actions/financeWallet", () => ({
  receiveDeposit: vi.fn().mockResolvedValue({ ok: true }),
  returnDeposit: vi.fn().mockResolvedValue({ ok: true }),
  settleWallet: vi.fn().mockResolvedValue({ ok: true }),
  forfeitWallet: vi.fn().mockResolvedValue({ ok: true }),
  voidPaymentVoucher: vi.fn().mockResolvedValue({ ok: true }),
  reverseWalletEntry: vi.fn().mockResolvedValue({ ok: true }),
  loadWalletEntries: vi.fn(),
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

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;

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

    expect(forfeitWallet).toHaveBeenCalledWith(
      "c1",
      { amount: "10", reason: "Damaged desk", request_key: expect.stringMatching(UUID) },
      undefined,
    );
  });

  it("voids a voucher with the reason and the two-factor code", async () => {
    render(<WalletManager wallets={[]} vouchers={[voucher]} customers={customers} invoices={[]} />);

    await userEvent.click(screen.getByRole("button", { name: "void" }));
    await userEvent.type(screen.getByLabelText(/voidReason/), "Paid twice");
    await userEvent.type(screen.getByLabelText(/fields.twoFactor/), "123456");
    await userEvent.click(screen.getAllByRole("button", { name: "void" }).at(-1)!);

    expect(voidPaymentVoucher).toHaveBeenCalledWith("v1", "Paid twice", "123456");
  });

  describe("request keys", () => {
    const keyOf = (fn: unknown, index = 0): string => (fn as ReturnType<typeof vi.fn>).mock.calls[index][1].request_key;
    const refused = { ok: false as const, message: "no", status: 500 };

    async function openDeposit() {
      render(<WalletManager wallets={[wallet]} vouchers={[]} customers={customers} invoices={[]} />);
      await userEvent.click(screen.getAllByRole("button", { name: "receive" }).at(-1)!);
    }

    it("sends a UUID with deposit, return and settlement", async () => {
      const invoices = [{ id: "i1", customer_id: "c1", invoice_number: "INV-1", remaining_amount: "30.00" }] as never;
      render(<WalletManager wallets={[wallet]} vouchers={[]} customers={customers} invoices={invoices} />);

      await userEvent.click(screen.getAllByRole("button", { name: "receive" }).at(-1)!);
      await userEvent.type(screen.getByLabelText(/fields.amount/), "5");
      await userEvent.click(screen.getByRole("button", { name: "save" }));
      await waitFor(() => expect(receiveDeposit).toHaveBeenCalled());

      await userEvent.click(screen.getByRole("button", { name: "return" }));
      await userEvent.type(screen.getByLabelText(/fields.amount/), "5");
      await userEvent.type(screen.getByLabelText(/fields.reason/), "Moved out");
      await userEvent.click(screen.getByRole("button", { name: "save" }));
      await waitFor(() => expect(returnDeposit).toHaveBeenCalled());

      await userEvent.click(screen.getByRole("button", { name: "settle" }));
      await userEvent.selectOptions(screen.getByLabelText(/fields.invoice/), "i1");
      await userEvent.type(screen.getByLabelText(/fields.amount/), "5");
      await userEvent.click(screen.getByRole("button", { name: "save" }));
      await waitFor(() => expect(settleWallet).toHaveBeenCalled());

      for (const fn of [receiveDeposit, returnDeposit, settleWallet]) expect(keyOf(fn)).toMatch(UUID);
    });

    it("keeps the key on a second submit of the same modal and changes it after reopening", async () => {
      vi.mocked(receiveDeposit).mockResolvedValueOnce(refused);
      await openDeposit();
      await userEvent.type(screen.getByLabelText(/fields.amount/), "5");

      await userEvent.click(screen.getByRole("button", { name: "save" }));
      await waitFor(() => expect(receiveDeposit).toHaveBeenCalledTimes(1));
      await userEvent.click(screen.getByRole("button", { name: "save" }));
      await waitFor(() => expect(receiveDeposit).toHaveBeenCalledTimes(2));
      expect(keyOf(receiveDeposit, 1)).toBe(keyOf(receiveDeposit, 0));

      // The second submit succeeded and closed the modal; open a new one.
      await userEvent.click(screen.getAllByRole("button", { name: "receive" }).at(-1)!);
      await userEvent.type(screen.getByLabelText(/fields.amount/), "5");
      await userEvent.click(screen.getByRole("button", { name: "save" }));
      await waitFor(() => expect(receiveDeposit).toHaveBeenCalledTimes(3));
      expect(keyOf(receiveDeposit, 2)).not.toBe(keyOf(receiveDeposit, 0));
    });

    it("does not offer cheque on a deposit", async () => {
      await openDeposit();
      const select = screen.getByLabelText(/fields.method/);

      expect(within(select).queryByRole("option", { name: "methods.cheque" })).not.toBeInTheDocument();
      expect(within(select).getAllByRole("option").map((o) => o.getAttribute("value"))).toEqual(["cash", "transfer", "card"]);
    });

    it("still offers cheque on a return", async () => {
      render(<WalletManager wallets={[wallet]} vouchers={[]} customers={customers} invoices={[]} />);
      await userEvent.click(screen.getByRole("button", { name: "return" }));

      expect(within(screen.getByLabelText(/fields.method/)).getByRole("option", { name: "methods.cheque" })).toBeInTheDocument();
    });
  });

  describe("entries and reversal", () => {
    const entry = (over: Partial<WalletEntry>): WalletEntry => ({
      id: "e1",
      customer_id: "c1",
      type: "deposit_in",
      amount: "40.00",
      method: "cash",
      reason: "Desk",
      related_type: null,
      related_id: null,
      created_by: null,
      created_at: "2026-02-01T10:00:00+00:00",
      reversible: true,
      reversed: false,
      ...over,
    });

    async function openEntries(items: WalletEntry[]) {
      vi.mocked(loadWalletEntries).mockResolvedValue({
        ok: true,
        data: { currency: "ILS", customer: wallet.customer, balance: "120.00", items, meta: {} as never },
      });
      render(<WalletManager wallets={[wallet]} vouchers={[]} customers={customers} invoices={[]} />);
      await userEvent.click(screen.getByRole("button", { name: "entries" }));
    }

    it("lists the entries, marks reversed ones and offers reverse only where allowed", async () => {
      await openEntries([entry({}), entry({ id: "e2", type: "forfeit", amount: "-5.00", reversible: false, reversed: true })]);

      expect(await screen.findByText("entryTypes.deposit_in")).toBeInTheDocument();
      expect(screen.getByText("statuses.reversed")).toBeInTheDocument();
      expect(screen.getAllByRole("button", { name: "reverse" })).toHaveLength(1);
    });

    it("sends the reason, a request key and the typed two-factor code", async () => {
      await openEntries([entry({})]);
      await userEvent.click(await screen.findByRole("button", { name: "reverse" }));

      const confirm = screen.getAllByRole("button", { name: "reverse" }).at(-1)!;
      expect(confirm).toBeDisabled();
      await userEvent.type(screen.getByLabelText(/fields.reason/), "Wrong amount");
      await userEvent.type(screen.getByLabelText(/fields.twoFactor/), "123456");
      await userEvent.click(confirm);

      expect(reverseWalletEntry).toHaveBeenCalledWith(
        "c1",
        "e1",
        { reason: "Wrong amount", request_key: expect.stringMatching(UUID) },
        "123456",
      );
    });
  });

  describe("last movement date", () => {
    it("renders the same Asia/Gaza calendar date on the server and in the browser", () => {
      const row: WalletRow = { ...wallet, last_entry_at: "2026-02-01T23:30:00+00:00" };
      const ui = <WalletManager wallets={[row]} vouchers={[]} customers={customers} invoices={[]} />;

      const server = renderToString(ui);
      const { container } = render(ui);

      expect(server).toContain("2026-02-02");
      expect(container.textContent).toContain("2026-02-02");
    });
  });
});
