"use client";

import type { ReactNode } from "react";

export interface SegmentedItem {
  id: string;
  label: ReactNode;
}

export interface SegmentedProps {
  items: SegmentedItem[];
  value: string;
  onChange: (id: string) => void;
}

/** Pill segmented control (filters, view switches). */
export function Segmented({ items, value, onChange }: SegmentedProps) {
  return (
    <div className="seg">
      {items.map((it) => (
        <button
          key={it.id}
          type="button"
          className={value === it.id ? "active" : ""}
          onClick={() => onChange(it.id)}
        >
          {it.label}
        </button>
      ))}
    </div>
  );
}
