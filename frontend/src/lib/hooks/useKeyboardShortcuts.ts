"use client";

import { createContext, useContext, useEffect, useRef } from "react";

export interface Shortcut {
  /** Unique within the registry; re-registering the same id replaces the entry. */
  id: string;
  /** Lower-case combo such as "alt+s", "mod+s" (Ctrl or Cmd), "f2", "?" or "t". */
  combo: string;
  /** Already-translated text shown in the help dialog. */
  description: string;
  handler: (event: KeyboardEvent) => void;
  /** Fire even while an input/textarea/select/contenteditable has focus. */
  allowInInput?: boolean;
}

export interface ShortcutsRegistry {
  register: (shortcut: Shortcut) => () => void;
}

export const ShortcutsContext = createContext<ShortcutsRegistry | null>(null);

const MODIFIER_ORDER = ["mod", "alt", "shift"] as const;

/** Canonical combo for a keyboard event, comparable with a normalized `Shortcut.combo`. */
export function eventCombo(event: KeyboardEvent): string {
  // Letters come from `code` because Alt/Option rewrites `key` on some layouts.
  const key = event.code?.startsWith("Key")
    ? event.code.slice(3).toLowerCase()
    : event.key.toLowerCase();
  const isSymbol = key.length === 1 && !/[a-z0-9]/.test(key);
  const active = {
    mod: event.ctrlKey || event.metaKey,
    alt: event.altKey,
    shift: event.shiftKey && !isSymbol,
  };
  const parts = MODIFIER_ORDER.filter((m) => active[m]);
  return [...parts, key].join("+");
}

/** Normalises a user-authored combo to the same shape `eventCombo` produces. */
export function normalizeCombo(combo: string): string {
  const parts = combo.toLowerCase().split("+");
  const key = parts.pop() ?? "";
  const mods = MODIFIER_ORDER.filter((m) => parts.includes(m));
  return [...mods, key].join("+");
}

/** True when the event originates from a field where typing must not trigger shortcuts. */
export function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;
  if (target.isContentEditable) return true;
  return ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName);
}

/**
 * Register page-local shortcuts for the lifetime of the calling component.
 * Handlers may close over fresh state: only the id/combo set drives re-registration.
 */
export function useKeyboardShortcuts(shortcuts: Shortcut[]): void {
  const registry = useContext(ShortcutsContext);
  const latest = useRef(shortcuts);

  useEffect(() => {
    latest.current = shortcuts;
  });

  const signature = shortcuts
    .map((s) => `${s.id}|${s.combo}|${s.description}|${s.allowInInput ? 1 : 0}`)
    .join(";");

  useEffect(() => {
    if (!registry) return;
    const unregister = latest.current.map((s) =>
      registry.register({
        ...s,
        handler: (event) =>
          latest.current.find((c) => c.id === s.id)?.handler(event),
      }),
    );
    return () => unregister.forEach((off) => off());
  }, [registry, signature]);
}
