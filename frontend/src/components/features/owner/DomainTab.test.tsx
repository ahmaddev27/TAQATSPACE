import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { DomainTab } from "./DomainTab";

const toast = vi.fn();
vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast }),
}));

const props = {
  slug: "acme",
  code: "AB12CD34",
  dashboardUrl: "https://app.test/owner",
  publicUrl: "https://app.test/w/acme",
};

describe("DomainTab", () => {
  it("shows the dashboard link, public link, slug and code", () => {
    render(<DomainTab {...props} />);

    expect(screen.getByText("https://app.test/owner")).toBeInTheDocument();
    expect(screen.getByText("https://app.test/w/acme")).toBeInTheDocument();
    expect(screen.getByText("acme")).toBeInTheDocument();
    expect(screen.getByText("AB12CD34")).toBeInTheDocument();
  });

  it("copies a value to the clipboard and confirms with a toast", async () => {
    const user = userEvent.setup();
    const writeText = vi.spyOn(navigator.clipboard, "writeText");
    render(<DomainTab {...props} />);

    await user.click(screen.getByRole("button", { name: "domainCopy: domainPublic" }));

    expect(writeText).toHaveBeenCalledWith("https://app.test/w/acme");
    expect(toast).toHaveBeenCalled();
  });
});
