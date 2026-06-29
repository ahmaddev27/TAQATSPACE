import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { PaymentsHistoryButton } from "./PaymentsHistoryButton";

const payments = [
  {
    id: "p1",
    amount: "100.00",
    paid_at: "2026-01-15T10:00:00Z",
    receipt_url: "https://example.com/r1.png",
  },
  {
    id: "p2",
    amount: "50.00",
    paid_at: "2026-02-20T10:00:00Z",
    receipt_url: null,
  },
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
] as any;

describe("PaymentsHistoryButton", () => {
  it("renders nothing when there are no payments", () => {
    const { container } = render(
      <PaymentsHistoryButton
        invoiceNumber="INV-1"
        payments={[]}
        currency="SAR"
      />,
    );
    expect(container).toBeEmptyDOMElement();
  });

  it("opens a modal listing payment rows with amount and date", async () => {
    const user = userEvent.setup();
    render(
      <PaymentsHistoryButton
        invoiceNumber="INV-1"
        payments={payments}
        currency="SAR"
      />,
    );

    await user.click(screen.getByRole("button"));

    expect(screen.getByRole("dialog")).toBeInTheDocument();
    expect(screen.getByText("2026-01-15")).toBeInTheDocument();
    expect(screen.getByText("2026-02-20")).toBeInTheDocument();
    expect(screen.getByText("SAR 100.00")).toBeInTheDocument();
    expect(screen.getByText("SAR 50.00")).toBeInTheDocument();
  });

  it("renders a ReceiptViewerButton only for payments that have a receipt", async () => {
    const user = userEvent.setup();
    render(
      <PaymentsHistoryButton
        invoiceNumber="INV-1"
        payments={payments}
        currency="SAR"
      />,
    );

    await user.click(screen.getByRole("button"));

    // One row has a receipt -> exactly one "view" receipt button inside the table.
    const viewButtons = screen.getAllByRole("button", { name: /view/i });
    expect(viewButtons).toHaveLength(1);
  });
});
