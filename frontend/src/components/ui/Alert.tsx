import type { ReactNode } from "react";
import { Icon, type IconName } from "./Icon";
import { cn } from "@/lib/cn";

type Tone = "info" | "success" | "warning" | "danger";

const TONE_ICON: Record<Tone, IconName> = {
  info: "bell",
  success: "checkCircle",
  warning: "alert",
  danger: "xCircle",
};

export interface AlertProps {
  tone?: Tone;
  title?: ReactNode;
  children?: ReactNode;
  icon?: IconName;
  className?: string;
}

export function Alert({ tone = "info", title, children, icon, className }: AlertProps) {
  return (
    <div className={cn("alert", `alert-${tone}`, className)} role="status">
      <span className="alert-ico">
        <Icon name={icon ?? TONE_ICON[tone]} size={18} />
      </span>
      <div className="grow">
        {title && <div className="alert-title">{title}</div>}
        {children && <div className="alert-body">{children}</div>}
      </div>
    </div>
  );
}
