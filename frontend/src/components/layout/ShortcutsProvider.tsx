"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import type { UserRole } from "@/lib/types/auth";
import {
  ShortcutsContext,
  eventCombo,
  isTypingTarget,
  normalizeCombo,
  useKeyboardShortcuts,
  type Shortcut,
  type ShortcutsRegistry,
} from "@/lib/hooks/useKeyboardShortcuts";
import { ShortcutsHelpModal } from "./ShortcutsHelpModal";

/** Owner jump targets; only routes that already exist are registered. */
const OWNER_JUMPS = [
  { id: "go-subscriptions", combo: "alt+s", href: "/owner/subscriptions", key: "goSubscriptions" },
  { id: "go-receipts", combo: "alt+r", href: "/owner/invoices", key: "goReceipts" },
] as const;

/** Submits the form the user is working in: focused one first, else the open dialog's. */
function submitActiveForm(): void {
  const active = document.activeElement;
  const form =
    (active instanceof HTMLElement ? active.closest("form") : null) ??
    document.querySelector<HTMLFormElement>(".modal form");
  form?.requestSubmit();
}

function focusSearch(): void {
  document.querySelector<HTMLInputElement>('form[role="search"] input')?.focus();
}

function BuiltinShortcuts({
  role,
  onHelp,
}: {
  role: UserRole;
  onHelp: () => void;
}) {
  const t = useTranslations("shortcuts");
  const router = useRouter();

  const shortcuts = useMemo<Shortcut[]>(() => {
    const base: Shortcut[] = [
      { id: "help", combo: "?", description: t("help"), handler: onHelp },
      {
        id: "refresh",
        combo: "r",
        description: t("refresh"),
        handler: () => router.refresh(),
      },
      {
        id: "save",
        combo: "mod+s",
        description: t("save"),
        handler: submitActiveForm,
        allowInInput: true,
      },
    ];
    if (role !== "workspace_owner") return base;
    const jumps = OWNER_JUMPS.map<Shortcut>((j) => ({
      id: j.id,
      combo: j.combo,
      description: t(j.key),
      handler: () => router.push(j.href),
    }));
    const search: Shortcut = {
      id: "search",
      combo: "alt+l",
      description: t("search"),
      handler: focusSearch,
    };
    return [...base, ...jumps, search];
  }, [role, router, onHelp, t]);

  useKeyboardShortcuts(shortcuts);
  return null;
}

export interface ShortcutsProviderProps {
  role: UserRole;
  children: ReactNode;
}

/**
 * Owns the single global keydown listener and the registry of active shortcuts.
 * Pages add local shortcuts with `useKeyboardShortcuts`; the help dialog (`?`)
 * lists whatever is registered at that moment.
 */
export function ShortcutsProvider({ role, children }: ShortcutsProviderProps) {
  const registryRef = useRef(new Map<string, Shortcut>());
  const [active, setActive] = useState<Shortcut[]>([]);
  const [helpOpen, setHelpOpen] = useState(false);

  const sync = useCallback(() => {
    setActive([...registryRef.current.values()]);
  }, []);

  const registry = useMemo<ShortcutsRegistry>(
    () => ({
      register(shortcut) {
        const entry = { ...shortcut, combo: normalizeCombo(shortcut.combo) };
        registryRef.current.set(entry.id, entry);
        sync();
        return () => {
          if (registryRef.current.get(entry.id) === entry) {
            registryRef.current.delete(entry.id);
            sync();
          }
        };
      },
    }),
    [sync],
  );

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.defaultPrevented || event.isComposing) return;
      const combo = eventCombo(event);
      const match = [...registryRef.current.values()]
        .reverse()
        .find((s) => s.combo === combo);
      if (!match) return;
      if (isTypingTarget(event.target) && !match.allowInInput) return;
      event.preventDefault();
      match.handler(event);
    };
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, []);

  const toggleHelp = useCallback(() => setHelpOpen((open) => !open), []);
  const closeHelp = useCallback(() => setHelpOpen(false), []);

  return (
    <ShortcutsContext.Provider value={registry}>
      <BuiltinShortcuts role={role} onHelp={toggleHelp} />
      {children}
      {helpOpen && <ShortcutsHelpModal shortcuts={active} onClose={closeHelp} />}
    </ShortcutsContext.Provider>
  );
}
