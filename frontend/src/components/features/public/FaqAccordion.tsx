"use client";

import { useState } from "react";
import { Icon } from "@/components/ui/Icon";
import { Reveal } from "@/components/ui/Reveal";
import { Segmented } from "@/components/ui/Segmented";
import type { PublicDict } from "./i18n";

interface QA {
  q: string;
  a: string;
}

export interface FaqAccordionProps {
  dict: PublicDict;
  freelancer?: QA[];
  owner?: QA[];
  /** Admin-managed flat Q/A list. When set, the segmented control is hidden. */
  flat?: QA[];
}

/** Role-segmented FAQ accordion (or a flat CMS list when `flat` is provided). */
export function FaqAccordion({ dict, freelancer = [], owner = [], flat }: FaqAccordionProps) {
  const f = dict.faq;
  const [segment, setSegment] = useState<"freelancer" | "owner">("freelancer");
  const [open, setOpen] = useState<number | null>(0);

  const items = flat ?? (segment === "freelancer" ? freelancer : owner);

  function selectSegment(id: string) {
    setSegment(id as "freelancer" | "owner");
    setOpen(0);
  }

  return (
    <div className="stack" style={{ gap: 24, maxWidth: 760, margin: "0 auto" }}>
      {!flat && (
        <div style={{ display: "flex", justifyContent: "center" }}>
          <Segmented
            value={segment}
            onChange={selectSegment}
            items={[
              { id: "freelancer", label: f.segFreelancer },
              { id: "owner", label: f.segOwner },
            ]}
          />
        </div>
      )}

      <div className="stack" style={{ gap: 12 }}>
        {items.map((item, i) => {
          const isOpen = open === i;
          return (
            <Reveal as="div" index={i} key={`${flat ? "cms" : segment}-${i}`}>
            <div className="card" style={{ overflow: "hidden" }}>
              <button
                type="button"
                className="between"
                aria-expanded={isOpen}
                onClick={() => setOpen(isOpen ? null : i)}
                style={{
                  width: "100%",
                  padding: "18px 20px",
                  background: "transparent",
                  border: "none",
                  cursor: "pointer",
                  font: "inherit",
                  color: "inherit",
                  textAlign: "start",
                }}
              >
                <span style={{ fontWeight: 600 }}>{item.q}</span>
                <Icon
                  name="chevD"
                  size={18}
                  style={{
                    transition: "transform var(--t-fast)",
                    transform: isOpen ? "rotate(180deg)" : "none",
                    flex: "none",
                  }}
                />
              </button>
              {isOpen && (
                <p className="muted" style={{ padding: "0 20px 18px", lineHeight: 1.8 }}>
                  {item.a}
                </p>
              )}
            </div>
            </Reveal>
          );
        })}
      </div>
    </div>
  );
}
