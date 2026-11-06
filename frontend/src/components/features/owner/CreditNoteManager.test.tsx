import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { CreditNoteManager } from "./CreditNoteManager";
import type { CreditNote } from "@/lib/types/financeCreditNotes";

const note: CreditNote = {
  id: "cn1",
  number: "WS-CRE-000001",
  invoice_id: "i1",
  invoice_number: "WS-INV-000007",
  customer: { id: "c1", name: "Lina" },
  amount: "30.00",
  reason: "Billed at the wrong price.",
  issued_by: { id: "o1", name: "Owner" },
  issued_at: "2026-02-01T10:00:00Z",
  voided_at: null,
  created_at: null,
};

describe("CreditNoteManager", () => {
  it("lists each credit note with its invoice, reason and a PDF link", () => {
    render(<CreditNoteManager creditNotes={[note]} />);

    expect(screen.getByText("WS-CRE-000001")).toBeInTheDocument();
    expect(screen.getByText("WS-INV-000007")).toBeInTheDocument();
    expect(screen.getByText("Billed at the wrong price.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /downloadPdf/ })).toHaveAttribute("href", "/api/credit-notes/cn1/pdf");
  });

  it("shows the empty state without credit notes", () => {
    render(<CreditNoteManager creditNotes={[]} />);

    expect(screen.getByText("empty")).toBeInTheDocument();
  });
});
