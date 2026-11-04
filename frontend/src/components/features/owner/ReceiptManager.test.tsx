import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ReceiptManager } from "./ReceiptManager";
import { changeChequeStatus } from "@/lib/actions/financeReceipts";
import type { Receipt } from "@/lib/types/financeReceipts";

const push = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
  usePathname: () => "/en/owner/finance/receipts",
}));

vi.mock("@/lib/actions/financeReceipts", () => ({
  createReceipt: vi.fn().mockResolvedValue({ ok: true }),
  changeChequeStatus: vi.fn().mockResolvedValue({ ok: true }),
  voidReceipt: vi.fn().mockResolvedValue({ ok: true }),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

const base: Receipt = {
  id: "r1",
  number: "WS-REC-000001",
  customer_id: "c1",
  customer: { id: "c1", name: "Lina" },
  method: "cheque",
  amount: "1000.00",
  allocated_amount: "600.00",
  unallocated_amount: "400.00",
  received_at: "2026-02-01T10:00:00Z",
  collected_at: null,
  reference: "CHQ-1",
  cheque_status: "deposited",
  is_collected: false,
  receipt_url: "https://api.test/finance/receipts/r1/file?signature=x",
  voided_at: null,
  void_reason: null,
  created_at: null,
};

describe("ReceiptManager", () => {
  beforeEach(() => vi.clearAllMocks());

  it("offers only the cheque moves that are allowed and sends the chosen one", async () => {
    render(<ReceiptManager receipts={[base]} sort="received_at" customers={[]} invoices={[]} />);

    expect(screen.getByText("WS-REC-000001")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "chequeActions.deposited" })).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: "chequeActions.cleared" }));
    expect(changeChequeStatus).toHaveBeenCalledWith("r1", "cleared");
  });

  it("links the private proof through its signed URL", () => {
    render(<ReceiptManager receipts={[base]} sort="received_at" customers={[]} invoices={[]} />);

    expect(screen.getByRole("link", { name: "viewProof" })).toHaveAttribute("href", base.receipt_url);
  });

  it("hides actions on a voided receipt", () => {
    render(
      <ReceiptManager
        receipts={[{ ...base, voided_at: "2026-02-02T10:00:00Z" }]}
        sort="received_at"
        customers={[]}
        invoices={[]}
      />,
    );

    expect(screen.queryByRole("button", { name: /chequeActions/ })).not.toBeInTheDocument();
    expect(screen.getByText("statuses.voided")).toBeInTheDocument();
  });

  it("switches the sort to the collection date", async () => {
    render(<ReceiptManager receipts={[base]} sort="received_at" customers={[]} invoices={[]} />);

    await userEvent.click(screen.getByRole("button", { name: "sort.collectedAt" }));
    expect(push).toHaveBeenCalledWith("/en/owner/finance/receipts?sort=collected_at");
  });
});
