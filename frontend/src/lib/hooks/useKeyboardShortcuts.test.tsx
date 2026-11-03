import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

const push = vi.fn();
const refresh = vi.fn();
vi.mock("@/i18n/navigation", () => ({
  useRouter: () => ({ push, refresh }),
}));

import { ShortcutsProvider } from "@/components/layout/ShortcutsProvider";
import { useKeyboardShortcuts } from "./useKeyboardShortcuts";

function Page({ onEdit, showInput = false }: { onEdit: () => void; showInput?: boolean }) {
  useKeyboardShortcuts([
    { id: "edit", combo: "F2", description: "Edit item", handler: onEdit },
    { id: "toggle", combo: "t", description: "Toggle", handler: onEdit },
  ]);
  return showInput ? <input aria-label="field" /> : null;
}

function setup(ui: React.ReactNode) {
  return render(<ShortcutsProvider role="workspace_owner">{ui}</ShortcutsProvider>);
}

describe("useKeyboardShortcuts", () => {
  it("fires a registered handler", async () => {
    const onEdit = vi.fn();
    setup(<Page onEdit={onEdit} />);
    await userEvent.keyboard("{F2}");
    expect(onEdit).toHaveBeenCalledTimes(1);
  });

  it("does not fire plain-key shortcuts while typing in a field", async () => {
    const onEdit = vi.fn();
    setup(<Page onEdit={onEdit} showInput />);
    await userEvent.type(screen.getByLabelText("field"), "t");
    expect(onEdit).not.toHaveBeenCalled();
  });

  it("stops firing after unmount", async () => {
    const onEdit = vi.fn();
    const view = setup(<Page onEdit={onEdit} />);
    view.rerender(<ShortcutsProvider role="workspace_owner">{null}</ShortcutsProvider>);
    await userEvent.keyboard("{F2}");
    expect(onEdit).not.toHaveBeenCalled();
  });

  it("navigates with Alt+S for owners", async () => {
    setup(null);
    await userEvent.keyboard("{Alt>}s{/Alt}");
    expect(push).toHaveBeenCalledWith("/owner/subscriptions");
  });

  it("lists active shortcuts in the help dialog on ?", async () => {
    setup(<Page onEdit={vi.fn()} />);
    await userEvent.keyboard("?");
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    expect(screen.getByText("Edit item")).toBeInTheDocument();
  });

  it("submits the open form on Ctrl+S, even from an input", async () => {
    const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());
    setup(
      <form onSubmit={onSubmit}>
        <input aria-label="name" />
      </form>,
    );
    await userEvent.type(screen.getByLabelText("name"), "x");
    await userEvent.keyboard("{Control>}s{/Control}");
    expect(onSubmit).toHaveBeenCalledTimes(1);
  });
});
