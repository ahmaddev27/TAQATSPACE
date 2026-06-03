"use client";

import { useState, type ReactNode } from "react";
import type { PublicDict } from "./i18n";

export interface DetailTabsProps {
  dict: PublicDict;
  overview: ReactNode;
  seats: ReactNode;
  reviews: ReactNode;
}

/** Tabbed body for the workspace detail page (Overview / Seats / Reviews). */
export function DetailTabs({ dict, overview, seats, reviews }: DetailTabsProps) {
  const d = dict.detail;
  const [tab, setTab] = useState<"overview" | "seats" | "reviews">("overview");

  const tabs: Array<{ id: typeof tab; label: string }> = [
    { id: "overview", label: d.overview },
    { id: "seats", label: d.seatsTab },
    { id: "reviews", label: d.reviewsTab },
  ];

  return (
    <>
      <div className="tabs" role="tablist" style={{ marginTop: 24 }}>
        {tabs.map((tb) => (
          <button
            key={tb.id}
            type="button"
            role="tab"
            aria-selected={tab === tb.id}
            className={`tab ${tab === tb.id ? "active" : ""}`}
            onClick={() => setTab(tb.id)}
          >
            {tb.label}
          </button>
        ))}
      </div>

      {tab === "overview" && overview}
      {tab === "seats" && seats}
      {tab === "reviews" && reviews}
    </>
  );
}
