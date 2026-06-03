"use client";

import { useEffect, type ReactNode } from "react";
import { Icon, type IconName } from "./Icon";

export interface ModalProps {
  title: ReactNode;
  onClose: () => void;
  children: ReactNode;
  footer?: ReactNode;
  icon?: IconName;
  closeLabel?: string;
}

/** Centered dialog with overlay. Closes on backdrop click and Escape. */
export function Modal({
  title,
  onClose,
  children,
  footer,
  icon,
  closeLabel = "Close",
}: ModalProps) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [onClose]);

  return (
    <div className="overlay" onClick={onClose}>
      <div
        className="modal"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
      >
        <div className="modal-head">
          <div className="row" style={{ gap: 12 }}>
            {icon && (
              <span className="st-ico">
                <Icon name={icon} />
              </span>
            )}
            <h3 className="h3">{title}</h3>
          </div>
          <button
            type="button"
            className="icon-btn"
            onClick={onClose}
            aria-label={closeLabel}
          >
            <Icon name="x" />
          </button>
        </div>
        <div className="modal-body">{children}</div>
        {footer && <div className="modal-foot">{footer}</div>}
      </div>
    </div>
  );
}
