import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ProfileWizard } from "./ProfileWizard";
import type { Member } from "@/lib/types";

const toast = vi.fn();
const updateMemberProfile = vi.fn();

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));
vi.mock("@/lib/actions/owner", () => ({
  updateMemberProfile: (...args: unknown[]) => updateMemberProfile(...args),
}));

const member = {
  subscription_id: "s1",
  user: { id: "u1", name: "Ali", email: "a@b.c", phone: null, specialty: null, avatar: null },
  status: "active",
  plan_type: "monthly",
  seat_number: null,
  monthly_price: 10,
  package: null,
  package_price: "0",
  join_date: null,
  cancelled_at: null,
  mikrotik_username: "ali.one",
  profile_completion: { percent: 0, missing: [] },
} as unknown as Member;

describe("ProfileWizard", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    updateMemberProfile.mockResolvedValue({ ok: true, data: undefined });
  });

  it("walks the three steps and saves the chosen values", async () => {
    const onDone = vi.fn();
    const user = userEvent.setup();
    render(<ProfileWizard member={member} onDone={onDone} onCancel={vi.fn()} />);

    await user.selectOptions(screen.getByLabelText("gender"), "female");
    await user.selectOptions(screen.getByLabelText("employmentStatus"), "student");
    await user.click(screen.getByRole("button", { name: "next" }));

    await user.selectOptions(screen.getByLabelText("educationLevel"), "bachelor");
    await user.selectOptions(screen.getByLabelText("specialization"), "nursing");
    await user.click(screen.getByRole("button", { name: "next" }));

    expect(screen.getByLabelText("mikrotikUsername")).toHaveValue("ali.one");
    await user.click(screen.getByRole("button", { name: "save" }));

    await waitFor(() =>
      expect(updateMemberProfile).toHaveBeenCalledWith("u1", {
        gender: "female",
        employment_status: "student",
        education_level: "bachelor",
        specialization: "nursing",
        mikrotik_username: "ali.one",
      }),
    );
    expect(onDone).toHaveBeenCalled();
  });

  it("shows the server error on the username step and stays open", async () => {
    updateMemberProfile.mockResolvedValue({
      ok: false,
      status: 422,
      message: "invalid",
      errors: { mikrotik_username: ["taken"] },
    });
    const onDone = vi.fn();
    const user = userEvent.setup();
    render(<ProfileWizard member={member} onDone={onDone} onCancel={vi.fn()} />);

    await user.click(screen.getByRole("button", { name: "next" }));
    await user.click(screen.getByRole("button", { name: "next" }));
    await user.click(screen.getByRole("button", { name: "save" }));

    expect(await screen.findByText("taken")).toBeInTheDocument();
    expect(onDone).not.toHaveBeenCalled();
  });

  it("calls onCancel from the first step", async () => {
    const onCancel = vi.fn();
    const user = userEvent.setup();
    render(<ProfileWizard member={member} onDone={vi.fn()} onCancel={onCancel} />);

    await user.click(screen.getByRole("button", { name: "cancel" }));

    expect(onCancel).toHaveBeenCalled();
  });
});
