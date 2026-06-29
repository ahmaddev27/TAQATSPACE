import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { Badge, StatusBadge } from "./Badge";

describe("Badge", () => {
  it("renders its children text", () => {
    render(<Badge>Active</Badge>);
    expect(screen.getByText("Active")).toBeInTheDocument();
  });
});

describe("StatusBadge", () => {
  it("renders the localized label for a known status", () => {
    render(<StatusBadge status="active" locale="en" />);
    expect(screen.getByText("Active")).toBeInTheDocument();
  });

  it("falls back to the raw status for an unknown value", () => {
    render(<StatusBadge status="mystery" locale="en" />);
    expect(screen.getByText("mystery")).toBeInTheDocument();
  });
});
