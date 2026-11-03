"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { useToast } from "@/components/providers/ToastProvider";
import {
  cancelApprovalRequest,
  submitOperationsSettings,
  type SeatTypeInput,
} from "@/lib/actions/owner";
import type { SeatingMode, SeatType, Workspace } from "@/lib/types";
import type { ApprovalRequestEntry } from "@/lib/types/approvals";

const DAY_KEYS = [
  "saturday",
  "sunday",
  "monday",
  "tuesday",
  "wednesday",
  "thursday",
  "friday",
] as const;

/** The three backend seat types, in display order. */
const SEAT_TYPE_KEYS: SeatType[] = ["flexible", "fixed", "private_office"];

const SEATING_MODES: SeatingMode[] = ["flexible", "fixed"];

interface DayHours {
  open: boolean;
  from: string;
  to: string;
}

/** Editable per-seat-type row (strings keep inputs controlled). */
interface SeatTypeRow {
  type: SeatType;
  enabled: boolean;
  priceMonthly: string;
  priceDaily: string;
  capacity: string;
}

export interface OperationsTabProps {
  workspace: Workspace;
  /** The owner's requests still awaiting a decision. */
  pending: ApprovalRequestEntry[];
}

function parseSeatTypes(raw: Workspace["seat_types"]): SeatTypeRow[] {
  const byType = new Map((raw ?? []).map((row) => [row.type, row]));
  return SEAT_TYPE_KEYS.map((type) => {
    const row = byType.get(type);
    return {
      type,
      enabled: row?.enabled ?? false,
      priceMonthly: row?.price_monthly ?? "",
      priceDaily: row?.price_daily ?? "",
      capacity: row?.capacity != null ? String(row.capacity) : "",
    };
  });
}

function parseHours(raw: Workspace["working_hours"]): Record<string, DayHours> {
  const source = (raw ?? {}) as Record<string, Partial<DayHours>>;
  const out: Record<string, DayHours> = {};
  for (const day of DAY_KEYS) {
    const d = source[day];
    out[day] = {
      open: d?.open ?? false,
      from: d?.from ?? "09:00",
      to: d?.to ?? "18:00",
    };
  }
  return out;
}

function toNullableNumber(value: string): number | null {
  const trimmed = value.trim();
  if (trimmed === "") return null;
  const n = Number(trimmed);
  return Number.isFinite(n) ? n : null;
}

interface ProposedSeatType {
  type: SeatType;
  [column: string]: unknown;
}

/** One "label: current → proposed" line per changed value of a pending request. */
function changeLines(
  entry: ApprovalRequestEntry,
  seatName: (type: SeatType) => string,
  labels: Record<string, string>,
): string[] {
  const current = (entry.change.current ?? {}) as Record<string, unknown>;
  const proposed = (entry.change.proposed ?? {}) as Record<string, unknown>;
  const lines: string[] = [];

  const before = (current.seat_types ?? []) as ProposedSeatType[];
  for (const row of (proposed.seat_types ?? []) as ProposedSeatType[]) {
    const old = before.find((b) => b.type === row.type) ?? { type: row.type };
    for (const column of Object.keys(row).filter((k) => k !== "type")) {
      lines.push(
        `${seatName(row.type)} · ${labels[column] ?? column}: ${format(old[column])} → ${format(row[column])}`,
      );
    }
  }

  for (const key of ["seating_mode", "tax_rate"]) {
    if (key in proposed) {
      lines.push(`${labels[key]}: ${format(current[key])} → ${format(proposed[key])}`);
    }
  }

  return lines;
}

function format(value: unknown): string {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "boolean") return value ? "✓" : "✗";
  return String(value);
}

/**
 * SET-OPS: capacity, seating mode, working hours, prices and tax. Saving never
 * changes live data; it submits requests that a platform admin approves.
 */
export function OperationsTab({ workspace, pending }: OperationsTabProps) {
  const t = useTranslations("owner");
  const ta = useTranslations("approvals");
  const { toast } = useToast();
  const [busy, startTransition] = useTransition();
  const [seatTypes, setSeatTypes] = useState(() => parseSeatTypes(workspace.seat_types));
  const [hours, setHours] = useState(() => parseHours(workspace.working_hours));
  const [seatingMode, setSeatingMode] = useState<SeatingMode>(workspace.seating_mode ?? "flexible");
  const [taxRate, setTaxRate] = useState(workspace.tax_rate ?? "0");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const setSeatType = (type: SeatType, patch: Partial<SeatTypeRow>) =>
    setSeatTypes((rows) => rows.map((row) => (row.type === type ? { ...row, ...patch } : row)));

  const setDay = (day: string, patch: Partial<DayHours>) =>
    setHours((h) => ({ ...h, [day]: { ...h[day], ...patch } }));

  const submit = () => {
    const rows: SeatTypeInput[] = seatTypes.map((row) => ({
      type: row.type,
      price_monthly: toNullableNumber(row.priceMonthly),
      price_daily: toNullableNumber(row.priceDaily),
      capacity: Math.max(0, Math.trunc(Number(row.capacity) || 0)),
      enabled: row.enabled,
    }));

    startTransition(async () => {
      const res = await submitOperationsSettings({
        seat_types: rows,
        seating_mode: seatingMode,
        working_hours: hours,
        tax_rate: Number(taxRate) || 0,
      });
      if (res.ok) {
        setErrors({});
        const created = res.data.requests.length;
        toast({
          tone: "ok",
          title: created > 0 ? t("operations.submitted") : t("operations.noChanges"),
        });
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("settings.saveFailed"), body: res.message });
      }
    });
  };

  const cancel = (id: string) =>
    startTransition(async () => {
      const res = await cancelApprovalRequest(id);
      if (res.ok) toast({ tone: "ok", title: t("operations.cancelled") });
      else toast({ tone: "err", title: t("settings.saveFailed"), body: res.message });
    });

  const labels: Record<string, string> = {
    capacity: t("settings.seatCapacity"),
    enabled: t("operations.enabled"),
    price_monthly: t("settings.seatMonthlyPrice"),
    price_daily: t("settings.seatDailyPrice"),
    seating_mode: t("operations.seatingMode"),
    tax_rate: t("operations.taxRate"),
  };
  const seatName = (type: SeatType) => t(`settings.seatTypeNames.${type}` as never);

  return (
    <div className="stack" style={{ gap: 22 }}>
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {t("operations.hint")}
      </p>

      {pending.length > 0 && (
        <div className="stack" style={{ gap: 10 }}>
          <h4 className="h4">{t("operations.pendingTitle")}</h4>
          {pending.map((entry) => (
            <div key={entry.id} className="seat-type-card is-on">
              <div className="between wrap" style={{ gap: 8 }}>
                <div className="row" style={{ gap: 8 }}>
                  <span className="hours-day">{ta(`types.${entry.type}`)}</span>
                  <Badge tone="warning" dot>
                    {t("operations.pendingBadge")}
                  </Badge>
                </div>
                <Button variant="secondary" size="sm" icon="x" disabled={busy} onClick={() => cancel(entry.id)}>
                  {t("operations.cancelRequest")}
                </Button>
              </div>
              {entry.type === "working_hours" ? (
                <p className="muted" style={{ fontSize: "var(--fs-sm)", marginTop: 6 }}>
                  {t("operations.hoursProposed")}
                </p>
              ) : (
                <ul className="stack ltr" style={{ gap: 2, marginTop: 6, fontSize: "var(--fs-sm)" }}>
                  {changeLines(entry, seatName, labels).map((line) => (
                    <li key={line}>{line}</li>
                  ))}
                </ul>
              )}
            </div>
          ))}
        </div>
      )}

      <section className="stack" style={{ gap: 12 }}>
        <h4 className="h4">{t("operations.capacityTitle")}</h4>
        <Field label={t("operations.seatingMode")} error={errors.seating_mode?.[0]}>
          <Select value={seatingMode} onChange={(e) => setSeatingMode(e.target.value as SeatingMode)}>
            {SEATING_MODES.map((mode) => (
              <option key={mode} value={mode}>
                {t(`operations.modes.${mode}` as never)}
              </option>
            ))}
          </Select>
        </Field>
        {seatTypes.map((row) => (
          <div key={row.type} className={`seat-type-card ${row.enabled ? "is-on" : ""}`}>
            <div className="seat-type-head">
              <Checkbox checked={row.enabled} onChange={(e) => setSeatType(row.type, { enabled: e.target.checked })}>
                <span className="hours-day">{seatName(row.type)}</span>
              </Checkbox>
            </div>
            {row.enabled && (
              <div className="seat-type-fields">
                <Field label={t("settings.seatCapacity")}>
                  <Input
                    className="tnum"
                    inputMode="numeric"
                    placeholder="0"
                    value={row.capacity}
                    onChange={(e) => setSeatType(row.type, { capacity: e.target.value })}
                  />
                </Field>
                <Field label={t("settings.seatMonthlyPrice")}>
                  <Input
                    className="tnum"
                    inputMode="decimal"
                    placeholder="0"
                    value={row.priceMonthly}
                    onChange={(e) => setSeatType(row.type, { priceMonthly: e.target.value })}
                  />
                </Field>
                <Field label={t("settings.seatDailyPrice")}>
                  <Input
                    className="tnum"
                    inputMode="decimal"
                    placeholder="0"
                    value={row.priceDaily}
                    onChange={(e) => setSeatType(row.type, { priceDaily: e.target.value })}
                  />
                </Field>
              </div>
            )}
          </div>
        ))}
        <Field label={t("operations.taxRate")} hint={t("operations.taxHint")} error={errors.tax_rate?.[0]}>
          <Input
            className="tnum ltr"
            inputMode="decimal"
            value={taxRate}
            onChange={(e) => setTaxRate(e.target.value)}
          />
        </Field>
      </section>

      <section className="stack" style={{ gap: 8 }}>
        <h4 className="h4">{t("operations.hoursTitle")}</h4>
        <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
          {t("settings.hoursHint")}
        </p>
        {DAY_KEYS.map((day) => {
          const d = hours[day];
          return (
            <div key={day} className={`hours-row ${d.open ? "is-open" : ""}`}>
              <Checkbox checked={d.open} onChange={(e) => setDay(day, { open: e.target.checked })}>
                <span className="hours-day">{t(`settings.days.${day}` as never)}</span>
              </Checkbox>
              {d.open ? (
                <div className="hours-times">
                  <Input type="time" className="tnum ltr" value={d.from} onChange={(e) => setDay(day, { from: e.target.value })} />
                  <span className="muted">{t("settings.to")}</span>
                  <Input type="time" className="tnum ltr" value={d.to} onChange={(e) => setDay(day, { to: e.target.value })} />
                </div>
              ) : (
                <span className="muted-3 hours-closed">{t("settings.closed")}</span>
              )}
            </div>
          );
        })}
      </section>

      <Button variant="primary" icon="check" loading={busy} onClick={submit} style={{ alignSelf: "flex-start" }}>
        {t("operations.submit")}
      </Button>
    </div>
  );
}
