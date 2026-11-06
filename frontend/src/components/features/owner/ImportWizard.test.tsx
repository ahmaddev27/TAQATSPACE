import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ImportBatch } from "@/lib/types/financeImports";
import { parseRowErrors } from "@/lib/types/financeImports";

const previewImport = vi.fn();
const commitImport = vi.fn();
const toastFn = vi.fn();

vi.mock("@/lib/actions/financeImports", () => ({
  previewImport: (...args: unknown[]) => previewImport(...args),
  commitImport: (...args: unknown[]) => commitImport(...args),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: toastFn }),
}));

import { ImportWizard } from "./ImportWizard";

const READY: ImportBatch = {
  id: "batch-1",
  type: "catalog_items",
  status: "previewed",
  row_count: 3,
  error_count: 0,
  errors: [],
  can_commit: true,
  committed_at: null,
};

async function reachThePreviewStep() {
  const user = userEvent.setup();
  const { container } = render(<ImportWizard />);
  const input = container.querySelector('input[type="file"]') as HTMLInputElement;
  await user.upload(input, new File(["x"], "items.xlsx"));
  await user.click(screen.getByRole("button", { name: "check" }));
  await screen.findByRole("button", { name: "commit" });
  return user;
}

describe("ImportWizard", () => {
  beforeEach(() => {
    previewImport.mockReset().mockResolvedValue({ ok: true, data: READY });
    commitImport.mockReset();
    toastFn.mockClear();
  });

  it("shows the rows of a refused commit with the same table as the preview errors", async () => {
    commitImport.mockResolvedValue({
      ok: false,
      status: 422,
      message: "The file has errors. Nothing was imported.",
      errors: { rows: ["5 (price): Use a number with at most two decimals.", "7: Repeated"] },
    });
    const user = await reachThePreviewStep();

    await user.click(screen.getByRole("button", { name: "commit" }));

    expect(await screen.findByText("Use a number with at most two decimals.")).toBeInTheDocument();
    expect(screen.getByText("price")).toBeInTheDocument();
    expect(screen.getByText("Repeated")).toBeInTheDocument();
    expect(screen.getByText("errorsTitle")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "commit" })).not.toBeInTheDocument();
    expect(toastFn).not.toHaveBeenCalled();
  });

  it("falls back to a toast when the refusal carries no rows", async () => {
    commitImport.mockResolvedValue({ ok: false, status: 409, message: "This file was already imported." });
    const user = await reachThePreviewStep();

    await user.click(screen.getByRole("button", { name: "commit" }));

    await waitFor(() => expect(toastFn).toHaveBeenCalledWith(expect.objectContaining({ tone: "err", body: "This file was already imported." })));
    expect(screen.getByRole("button", { name: "commit" })).toBeInTheDocument();
  });
});

describe("parseRowErrors", () => {
  it("reads the API lines back into rows, columns and messages", () => {
    expect(parseRowErrors(["5 (price): Bad: really", "7: Repeated", "not a row line"])).toEqual([
      { row: 5, column: "price", message: "Bad: really" },
      { row: 7, column: null, message: "Repeated" },
    ]);
  });

  it("is empty without lines", () => {
    expect(parseRowErrors(undefined)).toEqual([]);
  });
});
