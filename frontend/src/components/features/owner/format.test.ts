import { describe, it, expect } from "vitest";
import { avatarInitial, shortDate, money } from "./format";

describe("avatarInitial", () => {
  it("returns the uppercased first letter of a Latin name", () => {
    expect(avatarInitial("alice")).toBe("A");
    expect(avatarInitial("Bob")).toBe("B");
  });

  it("trims leading whitespace before taking the first grapheme", () => {
    expect(avatarInitial("   carol")).toBe("C");
  });

  it("preserves the first Arabic grapheme (no Latin uppercasing effect)", () => {
    expect(avatarInitial("أحمد")).toBe("أ");
    expect(avatarInitial("محمد")).toBe("م");
  });

  it("returns '?' for null, undefined and empty/whitespace input", () => {
    expect(avatarInitial(null)).toBe("?");
    expect(avatarInitial(undefined)).toBe("?");
    expect(avatarInitial("")).toBe("?");
    expect(avatarInitial("    ")).toBe("?");
  });

  it("handles a single character name", () => {
    expect(avatarInitial("x")).toBe("X");
  });
});

describe("shortDate", () => {
  it("formats an ISO datetime as YYYY-MM-DD", () => {
    expect(shortDate("2026-06-29T12:34:56Z")).toBe("2026-06-29");
  });

  it("zero-pads month and day", () => {
    expect(shortDate("2026-01-05T00:00:00Z")).toBe("2026-01-05");
  });

  it("returns the em dash for null/undefined/empty", () => {
    expect(shortDate(null)).toBe("—");
    expect(shortDate(undefined)).toBe("—");
    expect(shortDate("")).toBe("—");
  });

  it("returns the em dash for an unparseable date", () => {
    expect(shortDate("not-a-date")).toBe("—");
  });
});

describe("money", () => {
  it("formats a whole number with no decimals", () => {
    expect(money(100)).toBe("₪100");
    expect(money(0)).toBe("₪0");
  });

  it("formats a fractional number with two decimals", () => {
    expect(money(12.5)).toBe("₪12.50");
    expect(money(12.34)).toBe("₪12.34");
  });

  it("rounds to two decimals", () => {
    expect(money(12.345)).toBe("₪12.35");
    expect(money(12.999)).toBe("₪13");
  });

  it("parses numeric strings", () => {
    expect(money("250")).toBe("₪250");
    expect(money("19.9")).toBe("₪19.90");
  });

  it("defaults null/undefined to ₪0", () => {
    expect(money(null)).toBe("₪0");
    expect(money(undefined)).toBe("₪0");
  });

  it("returns ₪0 for non-numeric strings (NaN)", () => {
    expect(money("abc")).toBe("₪0");
  });

  it("handles negative amounts", () => {
    expect(money(-50)).toBe("₪-50");
  });
});
