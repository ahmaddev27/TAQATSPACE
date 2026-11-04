"use client";

import { useEffect, useRef } from "react";

/**
 * Call `callback` every `intervalMs` while the tab is visible. A hidden tab
 * skips its ticks so a forgotten background tab does not keep hitting the API.
 * The latest callback is always used without restarting the timer.
 */
export function usePolling(callback: () => void, intervalMs: number): void {
  const latest = useRef(callback);

  useEffect(() => {
    latest.current = callback;
  });

  useEffect(() => {
    const timer = window.setInterval(() => {
      if (document.visibilityState !== "hidden") latest.current();
    }, intervalMs);
    return () => window.clearInterval(timer);
  }, [intervalMs]);
}
