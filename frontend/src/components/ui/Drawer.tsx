"use client";

import { useEffect, type ReactNode } from "react";
import { Icon } from "./Icon";

export interface DrawerProps {
  title: ReactNode;
  onClose: () => void;
  children: ReactNode;
  footer?: ReactNode;
  closeLabel?: string;
}

/** Side panel sliding from the inline-end edge. Closes on backdrop + Escape. */
export function Drawer({
  title,
  onClose,
  children,
  footer,
  closeLabel = "Close",
}: DrawerProps) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [onClose]);

  return (
    <div className="drawer-wrap">
      <div
        className="overlay"
        onClick={onClose}
        style={{ position: "absolute" }}
      />
      <div className="drawer" role="dialog" aria-modal="true">
        <div className="modal-head">
          <h3 className="h3">{title}</h3>
          <button
            type="button"
            className="icon-btn"
            onClick={onClose}
            aria-label={closeLabel}
          >
            <Icon name="x" />
          </button>
        </div>
        <div className="modal-body grow" style={{ overflowY: "auto" }}>
          {children}
        </div>
        {footer && <div className="modal-foot">{footer}</div>}
      </div>
    </div>
  );
}
