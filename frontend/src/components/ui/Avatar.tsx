import type { CSSProperties } from "react";
import { cn } from "@/lib/cn";

export interface AvatarProps {
  initial: string;
  size?: "sm" | "md" | "lg";
  round?: boolean;
  tone?: string;
}

export function Avatar({ initial, size = "md", round, tone }: AvatarProps) {
  const sz = size === "lg" ? "lg" : size === "sm" ? "sm" : "";
  const style: CSSProperties | undefined = tone
    ? {
        background: `color-mix(in srgb, ${tone} 16%, var(--surface))`,
        color: tone,
      }
    : undefined;
  return (
    <span className={cn("avatar", sz, round && "round")} style={style}>
      {initial}
    </span>
  );
}
