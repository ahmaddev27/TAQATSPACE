import { forwardRef, type InputHTMLAttributes } from "react";
import { Icon, type IconName } from "./Icon";
import { cn } from "@/lib/cn";

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  icon?: IconName;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  { icon, className, ...rest },
  ref,
) {
  if (icon) {
    return (
      <div className="input-icon">
        <Icon name={icon} />
        <input ref={ref} className={cn("input", className)} {...rest} />
      </div>
    );
  }
  return <input ref={ref} className={cn("input", className)} {...rest} />;
});
