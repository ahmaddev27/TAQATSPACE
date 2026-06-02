import { forwardRef, type SelectHTMLAttributes } from "react";
import { Icon } from "./Icon";
import { cn } from "@/lib/cn";

export type SelectProps = SelectHTMLAttributes<HTMLSelectElement>;

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  { className, children, ...rest },
  ref,
) {
  return (
    <div className="select-wrap">
      <select ref={ref} className={cn("select", className)} {...rest}>
        {children}
      </select>
      <Icon name="chevD" size={16} />
    </div>
  );
});
