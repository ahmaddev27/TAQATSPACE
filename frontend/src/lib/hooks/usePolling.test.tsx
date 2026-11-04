import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { renderHook } from "@testing-library/react";
import { usePolling } from "./usePolling";

describe("usePolling", () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it("calls back on every interval and stops on unmount", () => {
    const callback = vi.fn();
    const { unmount } = renderHook(() => usePolling(callback, 30_000));

    vi.advanceTimersByTime(29_999);
    expect(callback).not.toHaveBeenCalled();

    vi.advanceTimersByTime(1);
    expect(callback).toHaveBeenCalledTimes(1);

    vi.advanceTimersByTime(60_000);
    expect(callback).toHaveBeenCalledTimes(3);

    unmount();
    vi.advanceTimersByTime(60_000);
    expect(callback).toHaveBeenCalledTimes(3);
  });

  it("uses the latest callback and skips ticks while the tab is hidden", () => {
    const first = vi.fn();
    const second = vi.fn();
    const { rerender } = renderHook(({ cb }) => usePolling(cb, 1000), { initialProps: { cb: first } });
    rerender({ cb: second });

    vi.advanceTimersByTime(1000);
    expect(first).not.toHaveBeenCalled();
    expect(second).toHaveBeenCalledTimes(1);

    vi.spyOn(document, "visibilityState", "get").mockReturnValue("hidden");
    vi.advanceTimersByTime(3000);
    expect(second).toHaveBeenCalledTimes(1);
  });
});
