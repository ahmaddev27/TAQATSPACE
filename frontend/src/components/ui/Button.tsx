import type { ButtonHTMLAttributes } from "react";
import { Icon, type IconName } from "./Icon";
import { cn } from "@/lib/cn";

type Variant = "primary" | "secondary" | "ghost" | "danger" | "accent";
type Size = "sm" | "md" | "lg";

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  icon?: IconName;
  iconEnd?: IconName;
  block?: boolean;
  loading?: boolean;
}

export function Button({
  variant = "primary",
  size = "md",
  icon,
  iconEnd,
  block,
  loading,
  className,
  children,
  disabled,
  type = "button",
  ...rest
}: ButtonProps) {
  const sz = size === "sm" ? "btn-sm" : size === "lg" ? "btn-lg" : "";
  return (
    <button
      type={type}
      className={cn(
        "btn",
        `btn-${variant}`,
        sz,
        block && "btn-block",
        loading && "is-loading",
        className,
      )}
      disabled={disabled || loading}
      aria-busy={loading}
      {...rest}
    >
      {loading ? (
        <span className="btn-spinner" aria-hidden="true" />
      ) : (
        icon && <Icon name={icon} />
      )}
      {children}
      {iconEnd && !loading && <Icon name={iconEnd} />}
    </button>
  );
}
