"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { SeatMap, type SeatMapSeat } from "@/components/ui/SeatMap";
import type { Seat } from "@/lib/types";

/** Per-type seat-number prefix, mirroring the backend SeatService. */
const TYPE_PREFIX: Record<string, string> = {
  flexible: "F",
  fixed: "X",
  private_office: "P",
};

export interface OwnerSeatSetupProps {
  /** Enabled seat types + their starting counts (from the seat-types step). */
  types: Array<{ type: string; count: number }>;
  /** Advance to the pending-review screen (after creating, or skipping). */
  onDone: () => void;
}

/**
 * Onboarding seat-creation step: the (pending) owner reviews/adjusts how many
 * seats of each type to create, generates them, sees the result, then continues
 * to the pending-review screen. Creation can be skipped and done after approval.
 */
export function OwnerSeatSetup({ types, onDone }: OwnerSeatSetupProps) {
  const tw = useTranslations("auth.registerWorkspace");
  const [counts, setCounts] = useState<Record<string, number>>(() =>
    Object.fromEntries(types.map((t) => [t.type, t.count])),
  );
  const [created, setCreated] = useState<Seat[] | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const previewFor = (type: string): string => {
    const n = counts[type] ?? 0;
    const prefix = TYPE_PREFIX[type] ?? type.charAt(0).toUpperCase();
    if (n <= 0) return "—";
    const pad = (i: number) => `${prefix}-${String(i).padStart(2, "0")}`;
    return n === 1 ? pad(1) : `${pad(1)} … ${pad(n)}`;
  };

  const totalSeats = types.reduce((sum, t) => sum + (counts[t.type] ?? 0), 0);

  const submit = async () => {
    setPending(true);
    setError(null);
    try {
      const res = await fetch("/api/auth/onboarding/seats", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({
          seats: types.map((t) => ({ type: t.type, count: counts[t.type] ?? 0 })),
        }),
      });
      const body = (await res.json().catch(() => ({}))) as {
        seats?: Seat[];
        message?: string;
      };
      if (res.ok) {
        setCreated(body.seats ?? []);
      } else {
        setError(body.message ?? tw("seatSetup.error"));
      }
    } catch {
      setError(tw("seatSetup.error"));
    } finally {
      setPending(false);
    }
  };

  if (created) {
    const mapSeats: SeatMapSeat[] = created.map((s) => ({
      id: s.id,
      label: s.seat_number,
      state: "available",
    }));
    return (
      <div className="card reg-card stack" style={{ gap: 18 }}>
        <div>
          <h3 className="h3">{tw("seatSetup.createdTitle")}</h3>
          <p className="muted" style={{ marginTop: 4 }}>
            {tw("seatSetup.createdBody", { count: created.length })}
          </p>
        </div>
        {mapSeats.length > 0 && <SeatMap seats={mapSeats} cols={8} />}
        <Button variant="primary" block icon="check" onClick={onDone}>
          {tw("seatSetup.continue")}
        </Button>
      </div>
    );
  }

  return (
    <div className="card reg-card stack" style={{ gap: 18 }}>
      <div>
        <h3 className="h3">{tw("seatSetup.title")}</h3>
        <p className="muted" style={{ marginTop: 4 }}>
          {tw("seatSetup.subtitle")}
        </p>
      </div>

      {error && <Alert tone="danger" title={error} />}

      <div className="stack" style={{ gap: 10 }}>
        {types.map((t) => (
          <div
            key={t.type}
            className="between"
            style={{
              gap: 12,
              padding: "12px 14px",
              border: "1px solid var(--border)",
              borderRadius: "var(--r-md)",
            }}
          >
            <div>
              <div style={{ fontWeight: 600 }}>{tw(`seatTypeNames.${t.type}`)}</div>
              <div className="muted-3 ltr tnum" style={{ fontSize: "var(--fs-xs)" }}>
                {previewFor(t.type)}
              </div>
            </div>
            <Input
              type="number"
              min={0}
              max={200}
              className="ltr tnum"
              style={{ width: 92 }}
              value={String(counts[t.type] ?? 0)}
              onChange={(e) =>
                setCounts((c) => ({
                  ...c,
                  [t.type]: Math.max(0, Math.trunc(Number(e.target.value) || 0)),
                }))
              }
              aria-label={tw(`seatTypeNames.${t.type}`)}
            />
          </div>
        ))}
      </div>

      <div className="row" style={{ gap: 10, justifyContent: "flex-end" }}>
        <Button variant="ghost" onClick={onDone}>
          {tw("seatSetup.skip")}
        </Button>
        <Button
          variant="primary"
          icon="check"
          loading={pending}
          disabled={totalSeats <= 0}
          onClick={submit}
        >
          {tw("seatSetup.create")}
        </Button>
      </div>
    </div>
  );
}
