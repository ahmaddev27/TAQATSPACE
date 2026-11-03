import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { EmptyState } from "./EmptyState";

vi.mock("@/i18n/navigation", () => ({
  Link: ({
    children,
    href,
    className,
  }: {
    children: React.ReactNode;
    href: string;
    className?: string;
  }) => (
    <a href={href} className={className}>
      {children}
    </a>
  ),
}));

describe("EmptyState", () => {
  it("renders title and body without an action", () => {
    render(<EmptyState title="Nothing here" body="Add something" />);

    expect(screen.getByText("Nothing here")).toBeInTheDocument();
    expect(screen.getByText("Add something")).toBeInTheDocument();
    expect(screen.queryByRole("button")).toBeNull();
    expect(screen.queryByRole("link")).toBeNull();
  });

  it("renders a link action when href is given", () => {
    render(
      <EmptyState title="t" action={{ label: "Go", href: "/owner/requests" }} />,
    );

    expect(screen.getByRole("link", { name: "Go" })).toHaveAttribute(
      "href",
      "/owner/requests",
    );
  });

  it("renders a button action that fires onClick", async () => {
    const onClick = vi.fn();
    render(<EmptyState title="t" action={{ label: "Add", onClick }} />);

    await userEvent.click(screen.getByRole("button", { name: "Add" }));

    expect(onClick).toHaveBeenCalledTimes(1);
  });
});
