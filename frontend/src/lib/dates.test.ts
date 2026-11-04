import { describe, it, expect } from "vitest";
import { formatDate, formatDateTime } from "./dates";

describe("dates", () => {
  it("shows a moment in the platform's time zone, whatever the machine's zone is", () => {
    expect(formatDateTime("2026-11-04T22:30:00Z")).toBe("2026-11-05 00:30");
  });

  it("uses the summer offset too", () => {
    expect(formatDateTime("2026-07-01T10:00:00Z")).toBe("2026-07-01 13:00");
  });

  it("writes midnight as 00, not 24", () => {
    expect(formatDateTime("2026-11-04T22:00:00Z")).toBe("2026-11-05 00:00");
  });

  it("formats the date alone", () => {
    expect(formatDate("2026-11-04T22:30:00Z")).toBe("2026-11-05");
  });
});
