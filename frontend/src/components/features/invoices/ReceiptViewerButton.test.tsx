import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ReceiptViewerButton } from "./ReceiptViewerButton";

describe("ReceiptViewerButton", () => {
  it("does not render the modal until the trigger is clicked", () => {
    render(<ReceiptViewerButton url="https://example.com/r.png" label="View" />);
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("opens a modal with an <img> for an image URL", async () => {
    const user = userEvent.setup();
    const url = "https://example.com/receipt.png";
    render(<ReceiptViewerButton url={url} label="View" />);

    await user.click(screen.getByRole("button", { name: /view/i }));

    expect(screen.getByRole("dialog")).toBeInTheDocument();
    const img = screen.getByRole("img");
    expect(img).toHaveAttribute("src", url);
  });

  it("renders an <iframe> for a .pdf URL", async () => {
    const user = userEvent.setup();
    const url = "https://example.com/receipt.pdf";
    const { container } = render(
      <ReceiptViewerButton url={url} label="View" />,
    );

    await user.click(screen.getByRole("button", { name: /view/i }));

    const iframe = container.querySelector("iframe");
    expect(iframe).not.toBeNull();
    expect(iframe).toHaveAttribute("src", url);
    expect(screen.queryByRole("img")).not.toBeInTheDocument();
  });

  it("points the open-original link at the url", async () => {
    const user = userEvent.setup();
    const url = "https://example.com/receipt.png";
    render(<ReceiptViewerButton url={url} label="View" />);

    await user.click(screen.getByRole("button", { name: /view/i }));

    const link = screen.getByRole("link");
    expect(link).toHaveAttribute("href", url);
    expect(link).toHaveAttribute("target", "_blank");
  });
});
