import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Select } from "./Select";

describe("Select", () => {
  it("renders its option children inside a select", () => {
    render(
      <Select aria-label="fruit">
        <option value="a">Apple</option>
        <option value="b">Banana</option>
      </Select>,
    );
    const select = screen.getByRole("combobox", { name: "fruit" });
    expect(select).toBeInTheDocument();
    expect(screen.getByRole("option", { name: "Apple" })).toBeInTheDocument();
    expect(screen.getByRole("option", { name: "Banana" })).toBeInTheDocument();
  });

  it("fires onChange with the chosen value", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <Select aria-label="fruit" defaultValue="a" onChange={onChange}>
        <option value="a">Apple</option>
        <option value="b">Banana</option>
      </Select>,
    );

    await user.selectOptions(
      screen.getByRole("combobox", { name: "fruit" }),
      "b",
    );

    expect(onChange).toHaveBeenCalled();
    const event = onChange.mock.calls[0][0];
    expect(event.target.value).toBe("b");
  });
});
