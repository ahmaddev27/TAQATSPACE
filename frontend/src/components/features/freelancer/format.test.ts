import { describe, it, expect } from "vitest";
import { formatDate, formatMoney } from "./format";

describe("formatDate", () => {
  it("extracts the date portion from an ISO datetime", () => {
    expect(formatDate("2026-06-28T15:00:00Z")).toBe("2026-06-28");
  });

  it("passes through an already date-only string", () => {
    expect(formatDate("2026-06-28")).toBe("2026-06-28");
  });

  it("returns the em dash for null/undefined/empty", () => {
    expect(formatDate(null)).toBe("—");
    expect(formatDate(undefined)).toBe("—");
    expect(formatDate("")).toBe("—");
  });

  it("returns the original value when the first 10 chars are not a valid date shape", () => {
    expect(formatDate("not-a-date-string")).toBe("not-a-date-string");
    expect(formatDate("12/06/2026")).toBe("12/06/2026");
  });
});

describe("formatMoney", () => {
  it("formats a whole number, trimming decimals, with default shekel currency", () => {
    expect(formatMoney(100)).toBe("₪100");
    expect(formatMoney(0)).toBe("₪0");
  });

  it("formats a fractional number with two decimals", () => {
    expect(formatMoney(12.5)).toBe("₪12.50");
    expect(formatMoney(12.34)).toBe("₪12.34");
  });

  it("parses numeric strings", () => {
    expect(formatMoney("250")).toBe("₪250");
    expect(formatMoney("19.9")).toBe("₪19.90");
  });

  it("returns currency + 0 for null/undefined", () => {
    expect(formatMoney(null)).toBe("₪0");
    expect(formatMoney(undefined)).toBe("₪0");
  });

  it("respects a custom currency symbol", () => {
    expect(formatMoney(50, "$")).toBe("$50");
    expect(formatMoney(null, "$")).toBe("$0");
  });

  it("echoes the raw amount with currency when it is non-numeric (NaN)", () => {
    expect(formatMoney("abc")).toBe("₪abc");
  });

  it("handles negative amounts", () => {
    expect(formatMoney(-50)).toBe("₪-50");
    expect(formatMoney(-12.5)).toBe("₪-12.50");
  });
});
