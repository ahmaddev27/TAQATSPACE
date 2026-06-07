"use client";

import { useState, type CSSProperties } from "react";
import { cn } from "@/lib/cn";

export interface AvatarProps {
  initial: string;
  /** Optional photo URL; falls back to the initial when missing or broken. */
  src?: string | null;
  /** Accessible label for the photo (e.g. the member's name). */
  alt?: string;
  size?: "sm" | "md" | "lg";
  round?: boolean;
  tone?: string;
}

export function Avatar({ initial, src, alt = "", size = "md", round, tone }: AvatarProps) {
  const [failed, setFailed] = useState(false);
  const sz = size === "lg" ? "lg" : size === "sm" ? "sm" : "";
  const showImage = Boolean(src) && !failed;
  const style: CSSProperties | undefined =
    tone && !showImage
      ? {
          background: `color-mix(in srgb, ${tone} 16%, var(--surface))`,
          color: tone,
        }
      : undefined;
  return (
    <span className={cn("avatar", sz, round && "round")} style={style}>
      {showImage ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={src ?? undefined}
          alt={alt}
          loading="lazy"
          onError={() => setFailed(true)}
          style={{ width: "100%", height: "100%", objectFit: "cover" }}
        />
      ) : (
        initial
      )}
    </span>
  );
}
