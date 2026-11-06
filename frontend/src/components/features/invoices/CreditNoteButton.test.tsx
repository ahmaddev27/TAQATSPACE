import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { CreditNoteButton } from "./CreditNoteButton";
import { issueCreditNote } from "@/lib/actions/financeCreditNotes";

vi.mock("@/lib/actions/financeCreditNotes", () => ({
  issueCreditNote: vi.fn(),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

const props = { invoiceId: "i1", invoiceNumber: "INV-1", creditable: "100.00" };

async function fill(amount: string, reason: string) {
  await userEvent.click(screen.getByRole("button", { name: "title" }));
  await userEvent.type(screen.getByRole("spinbutton"), amount);
  await userEvent.type(screen.getByRole("textbox"), reason);
  await userEvent.click(screen.getByRole("button", { name: "confirm" }));
}

describe("CreditNoteButton", () => {
  beforeEach(() => vi.clearAllMocks());

  it("sends the amount and reason", async () => {
    vi.mocked(issueCreditNote).mockResolvedValue({ ok: true } as never);
    render(<CreditNoteButton {...props} />);

    await fill("25.5", "Wrong price");

    expect(issueCreditNote).toHaveBeenCalledWith("i1", "25.5", "Wrong price", undefined, expect.any(String));
  });

  it("repeats one request key within an opened form and takes a new one when reopened", async () => {
    vi.mocked(issueCreditNote).mockResolvedValueOnce({ ok: false, status: 422, message: "x" } as never);
    render(<CreditNoteButton {...props} />);

    await fill("10", "Wrong price");
    vi.mocked(issueCreditNote).mockResolvedValue({ ok: true } as never);
    await userEvent.click(screen.getByRole("button", { name: "confirm" }));
    await fill("10", "Wrong price");

    const keys = vi.mocked(issueCreditNote).mock.calls.map((call) => call[4]);
    expect(keys).toHaveLength(3);
    expect(keys[0]).toBeTruthy();
    expect(keys[1]).toBe(keys[0]);
    expect(keys[2]).not.toBe(keys[0]);
  });

  it("asks for a two-factor code when the backend demands a step-up", async () => {
    vi.mocked(issueCreditNote).mockResolvedValue({ ok: false, status: 403, message: "x" } as never);
    render(<CreditNoteButton {...props} />);

    await fill("10", "Wrong price");

    expect(await screen.findByText("code")).toBeInTheDocument();
  });

  it("does not call the server without a reason", async () => {
    render(<CreditNoteButton {...props} />);

    await userEvent.click(screen.getByRole("button", { name: "title" }));
    await userEvent.type(screen.getByRole("spinbutton"), "10");
    await userEvent.click(screen.getByRole("button", { name: "confirm" }));

    expect(issueCreditNote).not.toHaveBeenCalled();
  });
});
