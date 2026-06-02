import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

export interface FieldProps {
  label?: ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  htmlFor?: string;
  optional?: boolean;
  optionalLabel?: string;
  children: ReactNode;
  className?: string;
}

export function Field({
  label,
  hint,
  error,
  htmlFor,
  optional,
  optionalLabel,
  children,
  className,
}: FieldProps) {
  return (
    <div className={cn("field", error && "is-error", className)}>
      {label && (
        <label className="label" htmlFor={htmlFor}>
          {label}
          {optional && optionalLabel && (
            <span className="label-opt"> {optionalLabel}</span>
          )}
        </label>
      )}
      {children}
      {(error || hint) && <span className="hint">{error || hint}</span>}
    </div>
  );
}
