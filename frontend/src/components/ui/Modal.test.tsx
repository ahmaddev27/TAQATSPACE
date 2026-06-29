import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Modal } from "./Modal";

function renderModal(onClose = vi.fn()) {
  render(
    <Modal title="My Title" onClose={onClose} footer={<span>My Footer</span>}>
      <p>Body content</p>
    </Modal>,
  );
  return onClose;
}

describe("Modal", () => {
  it("renders title, children, and footer", () => {
    renderModal();
    expect(screen.getByText("My Title")).toBeInTheDocument();
    expect(screen.getByText("Body content")).toBeInTheDocument();
    expect(screen.getByText("My Footer")).toBeInTheDocument();
  });

  it("calls onClose when Escape is pressed", async () => {
    const user = userEvent.setup();
    const onClose = renderModal();
    await user.keyboard("{Escape}");
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("calls onClose when the backdrop is clicked", async () => {
    const user = userEvent.setup();
    const onClose = renderModal();
    await user.click(document.querySelector(".overlay")!);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("does not call onClose when clicking inside the dialog", async () => {
    const user = userEvent.setup();
    const onClose = renderModal();
    await user.click(screen.getByRole("dialog"));
    expect(onClose).not.toHaveBeenCalled();
  });
});
