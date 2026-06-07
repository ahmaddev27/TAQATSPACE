"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { usePathname, useSearchParams } from "next/navigation";

/**
 * Slim top progress bar shown during client-side route changes.
 *
 * App Router navigations to a Server Component can take a moment while data
 * resolves; without feedback the UI feels frozen. This gives an instant,
 * dependency-free cue:
 *
 *  - A capture-phase click listener detects intent to navigate to an in-app
 *    URL and starts the bar immediately (before the route commits).
 *  - When `usePathname()` / `useSearchParams()` change, the navigation has
 *    committed, so the bar fills and fades out.
 *
 * The bar grows from the inline-start edge, so it animates right→left in RTL
 * and left→right in LTR automatically. Accessible via `role="progressbar"`.
 */

const TRICKLE_CEILING = 0.9; // never auto-fill past 90% until the route commits
const DONE_DELAY_MS = 220; // keep the full bar briefly so it reads as "done"
const SAFETY_TIMEOUT_MS = 12000; // force-reset if a navigation never commits

function isModifiedClick(event: MouseEvent): boolean {
  return (
    event.defaultPrevented ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  );
}

/** True when the anchor points to a different document inside this app. */
function isInternalNavigation(anchor: HTMLAnchorElement): boolean {
  if (anchor.target && anchor.target !== "_self") return false;
  if (anchor.hasAttribute("download")) return false;

  const href = anchor.getAttribute("href");
  if (!href || href.startsWith("#")) return false;
  if (/^(mailto:|tel:|[a-z]+:\/\/)/i.test(href) && anchor.origin !== window.location.origin) {
    return false;
  }
  if (anchor.origin !== window.location.origin) return false;

  // Same path + same query => not a navigation (e.g. hash-only or re-click).
  return (
    anchor.pathname !== window.location.pathname ||
    anchor.search !== window.location.search
  );
}

export function NavProgress() {
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const [progress, setProgress] = useState(0);
  const [active, setActive] = useState(false);
  const trickleRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const doneRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const safetyRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  // The route key in flight at the moment navigation started, so we only
  // complete once the *committed* route actually differs.
  const startedFromRef = useRef<string | null>(null);

  const reset = useCallback(() => {
    setActive(false);
    setProgress(0);
    startedFromRef.current = null;
  }, []);

  const clearTimers = useCallback(() => {
    if (trickleRef.current) clearInterval(trickleRef.current);
    if (doneRef.current) clearTimeout(doneRef.current);
    if (safetyRef.current) clearTimeout(safetyRef.current);
    trickleRef.current = null;
    doneRef.current = null;
    safetyRef.current = null;
  }, []);

  const start = useCallback(() => {
    if (startedFromRef.current !== null) return; // already running
    startedFromRef.current = `${window.location.pathname}?${window.location.search}`;
    clearTimers();
    setActive(true);
    setProgress(0.08);
    // Animated "trickle" toward the ceiling so fast and slow routes both feel alive.
    trickleRef.current = setInterval(() => {
      setProgress((p) => (p >= TRICKLE_CEILING ? p : p + (TRICKLE_CEILING - p) * 0.18));
    }, 200);
    // Defensive: clear the bar if a navigation is cancelled and never commits.
    safetyRef.current = setTimeout(reset, SAFETY_TIMEOUT_MS);
  }, [clearTimers, reset]);

  // Start the bar the moment the user commits to an internal navigation.
  useEffect(() => {
    const onClick = (event: MouseEvent) => {
      if (isModifiedClick(event)) return;
      const anchor = (event.target as Element | null)?.closest("a");
      if (anchor instanceof HTMLAnchorElement && isInternalNavigation(anchor)) {
        start();
      }
    };
    document.addEventListener("click", onClick, { capture: true });
    return () => document.removeEventListener("click", onClick, { capture: true });
  }, [start]);

  // Complete the bar once the route (path or query) has committed.
  useEffect(() => {
    if (startedFromRef.current === null) return;
    clearTimers();
    setProgress(1);
    doneRef.current = setTimeout(reset, DONE_DELAY_MS);
    // `reset`/`clearTimers` are stable; effect must track route changes only.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pathname, searchParams]);

  useEffect(() => clearTimers, [clearTimers]);

  return (
    <div
      className={`nav-progress ${active ? "is-active" : ""}`.trim()}
      role="progressbar"
      aria-hidden={!active}
      aria-busy={active}
      aria-label="Loading"
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={Math.round(progress * 100)}
    >
      <span
        className="nav-progress-bar"
        style={{ transform: `scaleX(${progress})` }}
      />
    </div>
  );
}
