import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { LogoManager } from "./LogoManager";

vi.mock("@/lib/actions/owner", () => ({
  uploadLogo: vi.fn().mockResolvedValue({ ok: true }),
  deleteLogo: vi.fn().mockResolvedValue({ ok: true }),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

vi.mock("@/components/ui/useImageCropper", () => ({
  useImageCropper: () => ({ cropFile: vi.fn(), cropper: null }),
}));

describe("LogoManager", () => {
  it("renders upload buttons and the remove button when a logo exists", () => {
    render(<LogoManager logo="/logo.png" hasLogo />);

    // Two upload affordances (label + button) plus the remove button.
    expect(
      screen.getAllByText("settings.uploadLogo").length,
    ).toBeGreaterThan(0);
    expect(screen.getByText("settings.removeLogo")).toBeInTheDocument();
  });

  it("hides the remove button when there is no logo", () => {
    render(<LogoManager logo={null} hasLogo={false} />);

    expect(
      screen.getAllByText("settings.uploadLogo").length,
    ).toBeGreaterThan(0);
    expect(screen.queryByText("settings.removeLogo")).not.toBeInTheDocument();
  });
});
