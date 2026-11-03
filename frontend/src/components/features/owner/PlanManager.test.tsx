import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { PlanManager } from "./PlanManager";
import { createPlan, updatePlan } from "@/lib/actions/financePlans";
import type { Plan } from "@/lib/types/financePlans";

vi.mock("@/lib/actions/financePlans", () => ({
  createPlan: vi.fn().mockResolvedValue({ ok: true }),
  updatePlan: vi.fn().mockResolvedValue({ ok: true }),
  archivePlan: vi.fn().mockResolvedValue({ ok: true }),
  removePlan: vi.fn().mockResolvedValue({ ok: true, data: { deleted: true } }),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

vi.mock("@/components/ui/ConfirmDialog", () => ({
  useConfirm: () => vi.fn().mockResolvedValue(true),
}));

const monthly: Plan = {
  id: "p1",
  code: "GOLD",
  name_ar: "ذهبي",
  name_en: "Gold",
  pricing_method: "monthly",
  price: "100.00",
  overage_price: null,
  included_quantity: null,
  validity_days: null,
  is_dedicated_seat: true,
  promo_price: "80.00",
  promo_until: "2099-01-01T23:59:59+00:00",
  effective_price: "80.00",
  is_archived: false,
  archived_at: null,
  created_at: null,
};

describe("PlanManager", () => {
  beforeEach(() => vi.clearAllMocks());

  it("lists plans with the effective price", () => {
    render(<PlanManager plans={[monthly]} />);

    expect(screen.getByText("GOLD")).toBeInTheDocument();
    expect(screen.getByText("Gold")).toBeInTheDocument();
    expect(screen.getByText("₪80")).toBeInTheDocument();
  });

  it("shows allowance and overage fields only for metered methods", async () => {
    const user = userEvent.setup();
    render(<PlanManager plans={[]} />);

    await user.click(screen.getByRole("button", { name: "add" }));
    expect(screen.queryByText("fields.overagePrice")).not.toBeInTheDocument();

    await user.selectOptions(screen.getAllByRole("combobox")[0], "hourly");
    expect(screen.getByText("fields.overagePrice")).toBeInTheDocument();
    expect(screen.getByText("fields.includedHours")).toBeInTheDocument();
  });

  it("disables the dedicated seat for a daily plan", async () => {
    const user = userEvent.setup();
    render(<PlanManager plans={[]} />);

    await user.click(screen.getByRole("button", { name: "add" }));
    await user.selectOptions(screen.getAllByRole("combobox")[0], "daily");

    expect(screen.getByRole("checkbox")).toBeDisabled();
  });

  it("creates a monthly plan", async () => {
    const user = userEvent.setup();
    render(<PlanManager plans={[]} />);

    await user.click(screen.getByRole("button", { name: "add" }));
    const [code, nameAr, nameEn] = screen.getAllByRole("textbox");
    await user.type(code, "BASIC");
    await user.type(nameAr, "أساسي");
    await user.type(nameEn, "Basic");
    await user.type(screen.getAllByRole("spinbutton")[0], "50");
    await user.click(screen.getByRole("button", { name: "save" }));

    expect(createPlan).toHaveBeenCalledWith(
      expect.objectContaining({ code: "BASIC", pricing_method: "monthly", price: "50", is_dedicated_seat: false }),
    );
  });

  it("locks the code when editing and omits it from the update", async () => {
    const user = userEvent.setup();
    render(<PlanManager plans={[monthly]} />);

    await user.click(screen.getByRole("button", { name: "edit" }));
    expect(screen.getAllByRole("textbox")[0]).toBeDisabled();
    await user.click(screen.getByRole("button", { name: "save" }));

    expect(updatePlan).toHaveBeenCalledTimes(1);
    expect(vi.mocked(updatePlan).mock.calls[0][1]).not.toHaveProperty("code");
  });
});
