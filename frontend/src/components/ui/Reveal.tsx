"use client";

import {
  useEffect,
  useRef,
  useState,
  type CSSProperties,
  type ElementType,
  type ReactNode,
} from "react";

export interface RevealProps {
  /** Element to render (default "div"). */
  as?: ElementType;
  /** Stagger order — drives the CSS `--i` delay. */
  index?: number;
  className?: string;
  children: ReactNode;
}

/**
 * Plays the `.u-reveal` entrance when the element scrolls into view. SSR-safe:
 * the server renders it visible (`is-in`), so no-JS / JS-error / reduced-motion
 * users never see hidden content. After hydration the client hides + reveals
 * only when it can observe and motion is allowed.
 */
export function Reveal({ as, index, className, children }: RevealProps) {
  const Tag = (as ?? "div") as ElementType;
  const ref = useRef<HTMLElement | null>(null);
  const [shown, setShown] = useState(true);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const reduce = window.matchMedia?.(
      "(prefers-reduced-motion: reduce)",
    ).matches;
    if (reduce || typeof IntersectionObserver === "undefined") return;

    const rect = el.getBoundingClientRect();
    const inView = rect.top < window.innerHeight && rect.bottom > 0;
    if (inView) return;

    setShown(false);
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            setShown(true);
            observer.disconnect();
          }
        }
      },
      { threshold: 0.1, rootMargin: "0px 0px -40px 0px" },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const classes = ["u-reveal", shown ? "is-in" : "", className ?? ""]
    .filter(Boolean)
    .join(" ");

  return (
    <Tag
      ref={ref as React.Ref<HTMLElement>}
      className={classes}
      style={index != null ? ({ "--i": index } as CSSProperties) : undefined}
    >
      {children}
    </Tag>
  );
}
