import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { StaffManager } from "./StaffManager";
import { inviteStaff, updateStaffPermissions } from "@/lib/actions/owner";
import type { Staff } from "@/lib/types/staff";

vi.mock("@/lib/actions/owner", () => ({
  inviteStaff: vi.fn().mockResolvedValue({ ok: true }),
  updateStaffPermissions: vi.fn().mockResolvedValue({ ok: true }),
  deactivateStaff: vi.fn().mockResolvedValue({ ok: true }),
  deleteStaffAccount: vi.fn().mockResolvedValue({ ok: true }),
  resendStaffInvitation: vi.fn().mockResolvedValue({ ok: true }),
  deleteStaffInvitation: vi.fn().mockResolvedValue({ ok: true }),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

vi.mock("@/components/ui/ConfirmDialog", () => ({
  useConfirm: () => vi.fn().mockResolvedValue(true),
}));

const member: Staff = {
  id: "s1",
  name: "Hana",
  email: "hana@cafe.ps",
  status: "active",
  staff_group: "hospitality",
  permissions: ["pos_sell", "pos_manage_products"],
  created_at: null,
};

const checkbox = (perm: string) =>
  screen.getByRole("checkbox", { name: `perms.${perm}` }) as HTMLInputElement;

describe("StaffManager", () => {
  beforeEach(() => vi.clearAllMocks());

  it("shows each member with their group badge and permissions", () => {
    render(<StaffManager staff={[member]} invitations={[]} />);

    expect(screen.getByText("Hana")).toBeInTheDocument();
    expect(screen.getByText("groups.hospitality")).toBeInTheDocument();
    expect(screen.getByText("perms.pos_manage_products")).toBeInTheDocument();
    expect(screen.getByText("noInvitations")).toBeInTheDocument();
  });

  it("pre-fills the group's permissions when inviting and sends the group", async () => {
    const user = userEvent.setup();
    render(<StaffManager staff={[]} invitations={[]} />);

    await user.click(screen.getByRole("button", { name: /^invite$/ }));
    // Default group is the cashier: sell + view reports.
    expect(checkbox("pos_sell").checked).toBe(true);
    expect(checkbox("pos_view_reports").checked).toBe(true);
    expect(checkbox("pos_refund").checked).toBe(false);

    await user.selectOptions(screen.getByRole("combobox"), "finance_supervisor");
    expect(checkbox("pos_refund").checked).toBe(true);
    expect(checkbox("pos_sell").checked).toBe(false);

    await user.click(checkbox("pos_sell"));
    await user.type(document.querySelector('input[type="email"]') as HTMLInputElement, "new@cafe.ps");
    await user.click(screen.getByRole("button", { name: "send" }));

    expect(inviteStaff).toHaveBeenCalledWith({
      email: "new@cafe.ps",
      name: undefined,
      staff_group: "finance_supervisor",
      permissions: ["pos_refund", "pos_view_reports", "pos_sell"],
    });
  });

  it("lets the owner adjust a single permission on top of the group", async () => {
    const user = userEvent.setup();
    render(<StaffManager staff={[member]} invitations={[]} />);

    await user.click(screen.getByRole("button", { name: "editPerms" }));
    expect(checkbox("pos_manage_products").checked).toBe(true);

    await user.click(checkbox("pos_manage_products"));
    await user.click(checkbox("pos_refund"));
    await user.click(screen.getByRole("button", { name: "save" }));

    expect(updateStaffPermissions).toHaveBeenCalledWith(
      "s1",
      ["pos_sell", "pos_refund"],
      "hospitality",
    );
  });
});
