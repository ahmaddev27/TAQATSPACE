import { describe, it, expect } from "vitest";
import { cn } from "./cn";

describe("cn", () => {
  it("joins multiple class strings with a space", () => {
    expect(cn("a", "b", "c")).toBe("a b c");
  });

  it("drops falsy values (false, null, undefined, empty string)", () => {
    expect(cn("a", false, null, undefined, "", "b")).toBe("a b");
  });

  it("returns an empty string when given no usable input", () => {
    expect(cn()).toBe("");
    expect(cn(false, null, undefined)).toBe("");
  });

  it("supports conditional object syntax", () => {
    expect(cn({ a: true, b: false, c: true })).toBe("a c");
  });

  it("flattens nested arrays", () => {
    expect(cn(["a", ["b", "c"]], "d")).toBe("a b c d");
  });

  it("resolves conflicting Tailwind utilities, keeping the last", () => {
    expect(cn("p-2", "p-4")).toBe("p-4");
    expect(cn("text-red-500", "text-blue-500")).toBe("text-blue-500");
  });

  it("keeps non-conflicting Tailwind utilities", () => {
    expect(cn("px-2", "py-4")).toBe("px-2 py-4");
  });

  it("merges conditional classes with conflict resolution", () => {
    expect(cn("p-2", { "p-4": true })).toBe("p-4");
  });
});
