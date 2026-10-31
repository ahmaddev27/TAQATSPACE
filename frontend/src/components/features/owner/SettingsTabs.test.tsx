import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { SettingsTabs } from "./SettingsTabs";
import type { Workspace } from "@/lib/types";

const toast = vi.fn();
const updateSettings = vi.fn();

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));
vi.mock("@/lib/actions/owner", () => ({
  updateSettings: (...args: unknown[]) => updateSettings(...args),
  updateSeatTypes: vi.fn(),
}));
vi.mock("next/dynamic", () => ({ default: () => () => null }));
vi.mock("./PhotoManager", () => ({ PhotoManager: () => null }));
vi.mock("./LogoManager", () => ({ LogoManager: () => null }));
vi.mock("./MessagingTab", () => ({ MessagingTab: () => null }));
vi.mock("./DomainTab", () => ({ DomainTab: () => null }));

const workspace = {
  id: "w1",
  name: "Acme",
  description: null,
  address: "Main st",
  city: "Gaza",
  city_id: "c1",
  phone: null,
  status: "active",
  is_published: true,
  amenities: [],
  working_hours: null,
  contact_email: "hi@acme.test",
  bank_name: "Bank A",
  iban: "GB82WEST12345698765432",
  timezone: "Asia/Riyadh",
  currency: "SAR",
  wifi_ssid: "Acme-WiFi",
  wifi_password: "pw-123",
} as unknown as Workspace;

describe("SettingsTabs general tab", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    updateSettings.mockResolvedValue({ ok: true });
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: [] }) }));
  });

  it("shows the saved general values", async () => {
    const user = userEvent.setup();
    render(<SettingsTabs workspace={workspace} locale="en" />);
    await user.click(screen.getByRole("tab", { name: "settings.tabGeneral" }));

    expect(screen.getByDisplayValue("hi@acme.test")).toBeInTheDocument();
    expect(screen.getByDisplayValue("GB82WEST12345698765432")).toBeInTheDocument();
    expect(screen.getByDisplayValue("Asia/Riyadh")).toBeInTheDocument();
    expect(screen.getByDisplayValue("SAR")).toBeInTheDocument();
    expect(screen.getByDisplayValue("Acme-WiFi")).toBeInTheDocument();
  });

  it("submits the edited general fields", async () => {
    const user = userEvent.setup();
    render(<SettingsTabs workspace={workspace} locale="en" />);
    await user.click(screen.getByRole("tab", { name: "settings.tabGeneral" }));

    const bank = screen.getByDisplayValue("Bank A");
    await user.clear(bank);
    await user.type(bank, "Bank B");
    await user.selectOptions(screen.getByDisplayValue("SAR"), "USD");
    await user.click(screen.getByRole("button", { name: "settings.save" }));

    await waitFor(() => expect(updateSettings).toHaveBeenCalled());
    expect(updateSettings.mock.calls[0][0]).toMatchObject({
      bank_name: "Bank B",
      currency: "USD",
      timezone: "Asia/Riyadh",
      iban: "GB82WEST12345698765432",
      wifi_password: "pw-123",
    });
  });

  it("shows server validation errors for the IBAN", async () => {
    updateSettings.mockResolvedValue({ ok: false, errors: { iban: ["bad iban"] }, message: "x" });
    const user = userEvent.setup();
    render(<SettingsTabs workspace={workspace} locale="en" />);
    await user.click(screen.getByRole("tab", { name: "settings.tabGeneral" }));
    await user.click(screen.getByRole("button", { name: "settings.save" }));

    expect(await screen.findByText("bad iban")).toBeInTheDocument();
  });
});
