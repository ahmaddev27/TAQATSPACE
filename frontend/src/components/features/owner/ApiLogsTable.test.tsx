import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen } from "@testing-library/react";
import { ApiLogsTable } from "./ApiLogsTable";
import type { RouterApiLog } from "@/lib/types/router";

const refresh = vi.fn();
const replace = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh, replace }),
  usePathname: () => "/owner/settings/api/diagnostics",
  useSearchParams: () => new URLSearchParams("response_code=500"),
}));

const log = (id: number, over: Partial<RouterApiLog> = {}): RouterApiLog => ({
  id,
  path: "/api/v1/hotspot/X/sync",
  response_code: 200,
  body_size: 120,
  duration_ms: 15,
  error_message: null,
  created_at: "2026-11-01T10:00:00Z",
  ...over,
});

const meta = { current_page: 1, per_page: 20, total: 2, last_page: 1 };

describe("ApiLogsTable", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("renders the requests with their code, timing and error", () => {
    render(<ApiLogsTable logs={[log(1), log(2, { response_code: 500, error_message: "Server error" })]} meta={meta} />);

    expect(screen.getAllByText("/api/v1/hotspot/X/sync")).toHaveLength(2);
    expect(screen.getByText("Server error")).toBeInTheDocument();
    expect(screen.getByText("500")).toBeInTheDocument();
    expect(screen.getAllByText("15 ms")).toHaveLength(2);
  });

  it("shows the active filter as a removable chip", () => {
    render(<ApiLogsTable logs={[]} meta={{ ...meta, total: 0 }} />);

    expect(screen.getByText("filter.response_code: 500")).toBeInTheDocument();
    expect(screen.getByText("empty")).toBeInTheDocument();
  });

  it("refreshes the page data every 30 seconds without a reload", () => {
    render(<ApiLogsTable logs={[log(1)]} meta={meta} />);

    vi.advanceTimersByTime(29_000);
    expect(refresh).not.toHaveBeenCalled();

    vi.advanceTimersByTime(1_000);
    expect(refresh).toHaveBeenCalledTimes(1);

    vi.advanceTimersByTime(30_000);
    expect(refresh).toHaveBeenCalledTimes(2);
  });
});
