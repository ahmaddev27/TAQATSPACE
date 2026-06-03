import type { ReactNode } from "react";

export interface ImgPlaceholderProps {
  label?: ReactNode;
  color?: string;
  h?: number | string;
  radius?: string;
  className?: string;
}

/** Striped image stand-in for layouts without real photos. */
export function ImgPlaceholder({
  label,
  color = "#dbe7f0",
  h = 180,
  radius = "var(--r-lg)",
  className = "",
}: ImgPlaceholderProps) {
  return (
    <div
      className={`imgph ${className}`.trim()}
      style={{ height: h, borderRadius: radius, background: color }}
    >
      {label && <span className="imgph-label">{label}</span>}
    </div>
  );
}
