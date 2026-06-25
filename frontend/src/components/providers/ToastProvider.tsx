"use client";

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { Icon, type IconName } from "@/components/ui/Icon";
import { Avatar } from "@/components/ui/Avatar";

export type ToastTone = "ok" | "err" | "info";

export interface Toast {
  id: number;
  tone: ToastTone;
  title: string;
  body?: string;
  /** Avatar image URL — shown instead of the tone icon (e.g. chat popups). */
  avatar?: string | null;
  /** Fallback avatar letter when no image — pairs with `avatar`. */
  initial?: string;
  /** Makes the toast clickable (e.g. open the chat). Fires then dismisses. */
  onClick?: () => void;
}

interface ToastContextValue {
  toast: (t: {
    tone?: ToastTone;
    title: string;
    body?: string;
    avatar?: string | null;
    initial?: string;
    onClick?: () => void;
  }) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

const TONE_ICON: Record<ToastTone, IconName> = {
  ok: "checkCircle",
  err: "xCircle",
  info: "bell",
};

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const counter = useRef(0);

  const remove = useCallback((id: number) => {
    setToasts((list) => list.filter((t) => t.id !== id));
  }, []);

  const toast = useCallback<ToastContextValue["toast"]>(
    ({ tone = "info", title, body, avatar, initial, onClick }) => {
      const id = ++counter.current;
      setToasts((list) => [
        ...list,
        { id, tone, title, body, avatar, initial, onClick },
      ]);
      setTimeout(() => remove(id), 4000);
    },
    [remove],
  );

  const value = useMemo(() => ({ toast }), [toast]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className="toast-host">
        {toasts.map((t) => {
          const clickable = Boolean(t.onClick);
          const hasAvatar = t.avatar != null || t.initial != null;
          return (
            <div
              key={t.id}
              className={`toast ${t.tone}`}
              role={clickable ? "button" : undefined}
              tabIndex={clickable ? 0 : undefined}
              style={clickable ? { cursor: "pointer" } : undefined}
              onClick={
                clickable
                  ? () => {
                      t.onClick?.();
                      remove(t.id);
                    }
                  : undefined
              }
              onKeyDown={
                clickable
                  ? (e) => {
                      if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        t.onClick?.();
                        remove(t.id);
                      }
                    }
                  : undefined
              }
            >
              {hasAvatar ? (
                <Avatar
                  src={t.avatar ?? undefined}
                  initial={t.initial ?? "?"}
                  alt={t.title}
                  round
                />
              ) : (
                <span className="t-ico">
                  <Icon name={TONE_ICON[t.tone]} size={18} />
                </span>
              )}
              <div className="grow">
                <div style={{ fontWeight: 600, fontSize: "var(--fs-sm)" }}>
                  {t.title}
                </div>
                {t.body && (
                  <div className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
                    {t.body}
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error("useToast must be used within a ToastProvider");
  return ctx;
}
