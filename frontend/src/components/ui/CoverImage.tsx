"use client";

import { useState } from "react";

const DEFAULT_FALLBACK = "/images/workspaces/placeholder-1.svg";

export interface CoverImageProps {
  src: string;
  alt?: string;
  h: number | string;
  radius?: string;
  className?: string;
  /** Shown if `src` fails to load (e.g. a stale/missing photo path). */
  fallbackSrc?: string;
}

/**
 * A cover `<img>` that fills its slot (object-fit: cover) and gracefully falls
 * back to an on-brand placeholder when the real photo is missing or broken.
 */
export function CoverImage({
  src,
  alt = "",
  h,
  radius = "var(--r-lg)",
  className = "",
  fallbackSrc = DEFAULT_FALLBACK,
}: CoverImageProps) {
  const [failed, setFailed] = useState(false);
  const url = failed ? fallbackSrc : src;
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={url}
      alt={alt}
      loading="lazy"
      onError={() => {
        if (!failed) setFailed(true);
      }}
      className={className}
      style={{
        width: "100%",
        height: h,
        objectFit: "cover",
        borderRadius: radius,
        display: "block",
      }}
    />
  );
}
