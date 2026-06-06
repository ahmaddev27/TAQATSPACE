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
import { useTranslations } from "next-intl";
import { Modal } from "./Modal";
import { Button } from "./Button";
import type { IconName } from "./Icon";

/* -------------------------------------------------------------------------- */
/*  Types                                                                      */
/* -------------------------------------------------------------------------- */

export type ConfirmTone = "default" | "danger";

export interface ConfirmOptions {
  /** Dialog heading. */
  title: ReactNode;
  /** Body copy explaining the consequence of confirming. */
  message: ReactNode;
  /** Confirm button label. Defaults to the shared `common.confirm`. */
  confirmLabel?: string;
  /** Cancel button label. Defaults to the shared `common.cancel`. */
  cancelLabel?: string;
  /** `danger` renders a red confirm button for destructive actions. */
  tone?: ConfirmTone;
  /** Optional header icon. */
  icon?: IconName;
  /**
   * Optional async action run when the user confirms. While it runs the confirm
   * button shows a spinner and both buttons are disabled; the dialog only closes
   * once it settles. Omit it to use the plain promise flow (resolves on confirm,
   * caller runs the work).
   */
  onConfirm?: () => void | Promise<void>;
}

interface ConfirmState extends ConfirmOptions {
  resolve: (confirmed: boolean) => void;
}

interface ConfirmContextValue {
  /** Open the dialog; resolves `true` on confirm, `false` on cancel/dismiss. */
  confirm: (options: ConfirmOptions) => Promise<boolean>;
}

const ConfirmContext = createContext<ConfirmContextValue | null>(null);

/* -------------------------------------------------------------------------- */
/*  Provider                                                                   */
/* -------------------------------------------------------------------------- */

/**
 * App-wide styled confirmation dialog, replacing the native `window.confirm`.
 *
 * Exposes a promise-based `confirm()` via {@link useConfirm}, so callers keep
 * the familiar `if (await confirm(...))` flow while getting a design-system
 * modal: RTL-correct, Escape/backdrop dismissible, a `danger` (red) variant for
 * destructive actions, and async-aware — the confirm button shows a spinner and
 * both buttons disable while an awaited handler runs.
 */
export function ConfirmProvider({ children }: { children: ReactNode }) {
  const tc = useTranslations("common");
  const [state, setState] = useState<ConfirmState | null>(null);
  const [busy, setBusy] = useState(false);
  // Hold the active resolver so dismissing (Escape/backdrop) still settles it.
  const pending = useRef<((confirmed: boolean) => void) | null>(null);

  const settle = useCallback((confirmed: boolean) => {
    pending.current?.(confirmed);
    pending.current = null;
    setBusy(false);
    setState(null);
  }, []);

  const confirm = useCallback<ConfirmContextValue["confirm"]>((options) => {
    // Reject any previously open prompt as cancelled before opening a new one.
    pending.current?.(false);

    return new Promise<boolean>((resolve) => {
      pending.current = resolve;
      setState({ ...options, resolve });
    });
  }, []);

  const onConfirm = useCallback(async () => {
    const action = state?.onConfirm;

    // No async action: resolve immediately and let the caller do the work.
    if (!action) {
      settle(true);
      return;
    }

    // Async action: keep the dialog open with a spinner until it settles.
    setBusy(true);
    try {
      await action();
      settle(true);
    } catch {
      // Surface failures to the caller's promise; leave UI feedback to them.
      settle(false);
    }
  }, [state, settle]);

  const onCancel = useCallback(() => {
    if (busy) return;
    settle(false);
  }, [busy, settle]);

  const value = useMemo(() => ({ confirm }), [confirm]);

  return (
    <ConfirmContext.Provider value={value}>
      {children}
      {state && (
        <Modal
          title={state.title}
          icon={state.icon}
          onClose={onCancel}
          closeLabel={state.cancelLabel ?? tc("cancel")}
          footer={
            <>
              <Button variant="ghost" onClick={onCancel} disabled={busy}>
                {state.cancelLabel ?? tc("cancel")}
              </Button>
              <Button
                variant={state.tone === "danger" ? "danger" : "primary"}
                loading={busy}
                onClick={onConfirm}
              >
                {state.confirmLabel ?? tc("confirm")}
              </Button>
            </>
          }
        >
          <p className="muted" style={{ lineHeight: 1.6 }}>
            {state.message}
          </p>
        </Modal>
      )}
    </ConfirmContext.Provider>
  );
}

/* -------------------------------------------------------------------------- */
/*  Hook                                                                       */
/* -------------------------------------------------------------------------- */

export function useConfirm(): ConfirmContextValue["confirm"] {
  const ctx = useContext(ConfirmContext);
  if (!ctx) {
    throw new Error("useConfirm must be used within a ConfirmProvider");
  }
  return ctx.confirm;
}
