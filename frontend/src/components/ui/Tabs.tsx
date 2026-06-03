"use client";

import type { ReactNode } from "react";

export interface TabItem {
  id: string;
  label: ReactNode;
}

export interface TabsProps {
  items: TabItem[];
  value: string;
  onChange: (id: string) => void;
}

/** Underlined tab strip. */
export function Tabs({ items, value, onChange }: TabsProps) {
  return (
    <div className="tabs" role="tablist">
      {items.map((it) => (
        <button
          key={it.id}
          type="button"
          role="tab"
          aria-selected={value === it.id}
          className={`tab ${value === it.id ? "active" : ""}`.trim()}
          onClick={() => onChange(it.id)}
        >
          {it.label}
        </button>
      ))}
    </div>
  );
}
