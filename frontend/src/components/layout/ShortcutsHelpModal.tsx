"use client";

import { useTranslations } from "next-intl";
import { Modal } from "@/components/ui/Modal";
import type { Shortcut } from "@/lib/hooks/useKeyboardShortcuts";

export interface ShortcutsHelpModalProps {
  shortcuts: Shortcut[];
  onClose: () => void;
}

/** Display form of a normalized combo, e.g. "mod+s" -> "Ctrl + S". */
function formatCombo(combo: string): string {
  return combo
    .split("+")
    .map((part) => (part === "mod" ? "Ctrl" : part.length === 1 ? part.toUpperCase() : part[0].toUpperCase() + part.slice(1)))
    .join(" + ");
}

/** Lists every shortcut currently registered, so it never advertises a missing page's keys. */
export function ShortcutsHelpModal({ shortcuts, onClose }: ShortcutsHelpModalProps) {
  const t = useTranslations("shortcuts");

  return (
    <Modal title={t("title")} icon="settings" onClose={onClose} closeLabel={t("close")}>
      <p className="muted" style={{ marginBottom: 12 }}>
        {t("hint")}
      </p>
      <ul className="stack" style={{ gap: 8, listStyle: "none", padding: 0 }}>
        {shortcuts.map((s) => (
          <li key={s.id} className="row" style={{ justifyContent: "space-between" }}>
            <span>{s.description}</span>
            <kbd dir="ltr">{formatCombo(s.combo)}</kbd>
          </li>
        ))}
      </ul>
    </Modal>
  );
}
