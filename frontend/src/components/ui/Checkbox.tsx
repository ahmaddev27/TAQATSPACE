import { forwardRef, type InputHTMLAttributes, type ReactNode } from "react";
import { cn } from "@/lib/cn";

export interface CheckboxProps
  extends Omit<InputHTMLAttributes<HTMLInputElement>, "type"> {
  children?: ReactNode;
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  function Checkbox({ children, className, ...rest }, ref) {
    return (
      <label className={cn("check", className)}>
        <input ref={ref} type="checkbox" {...rest} />
        {children}
      </label>
    );
  },
);
