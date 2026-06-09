"use client";

import { useEffect } from "react";

/**
 * Subtle scroll-reveal for the landing page: gently fades and raises every
 * element marked with `data-reveal` as it enters the viewport.
 *
 * The hidden starting state is applied only after this mounts (via a
 * `reveal-active` flag on <html>), so the page stays fully visible during SSR,
 * without JavaScript, and for visitors who prefer reduced motion. Renders
 * nothing — it only wires up the IntersectionObserver.
 */
export function ScrollReveal() {
  useEffect(() => {
    const items = Array.from(
      document.querySelectorAll<HTMLElement>("[data-reveal]"),
    );
    if (items.length === 0) return;

    const prefersReduced = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;
    if (prefersReduced || typeof IntersectionObserver === "undefined") return;

    const root = document.documentElement;
    root.classList.add("reveal-active");

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-revealed");
            observer.unobserve(entry.target);
          }
        }
      },
      { rootMargin: "0px 0px -8% 0px", threshold: 0.06 },
    );

    for (const el of items) observer.observe(el);

    return () => {
      observer.disconnect();
      root.classList.remove("reveal-active");
    };
  }, []);

  return null;
}
