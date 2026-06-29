import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { Avatar } from "./Avatar";

describe("Avatar", () => {
  it("shows the initial when no image source is provided", () => {
    render(<Avatar initial="A" />);
    expect(screen.getByText("A")).toBeInTheDocument();
  });

  it("renders an image when a source is provided", () => {
    render(<Avatar initial="A" src="https://example.com/p.png" alt="Alice" />);
    const img = screen.getByRole("img", { name: "Alice" });
    expect(img).toHaveAttribute("src", "https://example.com/p.png");
  });
});
