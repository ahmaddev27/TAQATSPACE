import type { ReactNode } from "react";
import { Link } from "@/i18n/navigation";
import { Button } from "./Button";
import { Icon, type IconName } from "./Icon";

export interface EmptyStateAction {
  label: string;
  /** Navigates when set; otherwise `onClick` runs. */
  href?: string;
  onClick?: () => void;
  icon?: IconName;
  variant?: "primary" | "secondary";
}

export interface EmptyStateProps {
  title: ReactNode;
  body?: ReactNode;
  icon?: IconName;
  action?: EmptyStateAction;
}

/** Guided empty state: what is missing, why, and the one action that fixes it. */
export function EmptyState({ title, body, icon, action }: EmptyStateProps) {
  const variant = action?.variant ?? "primary";

  return (
    <div className="empty-state">
      {icon && (
        <div className="st-ico" style={{ width: 48, height: 48 }}>
          <Icon name={icon} />
        </div>
      )}
      <div>
        <div style={{ fontWeight: 600, color: "var(--text-2)" }}>{title}</div>
        {body && <div style={{ fontSize: "var(--fs-sm)" }}>{body}</div>}
      </div>
      {action &&
        (action.href ? (
          <Link href={action.href} className={`btn btn-${variant} btn-sm`}>
            {action.icon && <Icon name={action.icon} />}
            {action.label}
          </Link>
        ) : (
          <Button
            variant={variant}
            size="sm"
            icon={action.icon}
            onClick={action.onClick}
          >
            {action.label}
          </Button>
        ))}
    </div>
  );
}
