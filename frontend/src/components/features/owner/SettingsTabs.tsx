"use client";

import { useState, useTransition } from "react";
import dynamic from "next/dynamic";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Badge, StatusBadge } from "@/components/ui/Badge";
import { Tabs } from "@/components/ui/Tabs";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import {
  updateSeatTypes,
  updateSettings,
  type SeatTypeInput,
  type WorkspaceSettingsInput,
} from "@/lib/actions/owner";
import type { SeatType, Workspace } from "@/lib/types";
import { PhotoManager } from "./PhotoManager";
import { MessagingTab } from "./MessagingTab";

export interface SettingsTabsProps {
  workspace: Workspace;
  locale: string;
}

// MapLibre touches `window`, so load the picker on the client only.
const LocationPicker = dynamic(
  () => import("@/components/features/public/LocationPicker").then((m) => m.LocationPicker),
  {
    ssr: false,
    loading: () => (
      <div className="map-canvas">
        <div className="map-fallback" style={{ height: 300 }} />
      </div>
    ),
  },
);

const AMENITY_KEYS = [
  "wifi",
  "parking",
  "coffee",
  "meeting_room",
  "printer",
  "ac",
  "kitchen",
  "lockers",
  "reception",
  "security",
] as const;

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

interface DayHours {
  open: boolean;
  from: string;
  to: string;
}

/** Editable per-seat-type pricing row (strings keep inputs controlled). */
interface SeatTypeRow {
  type: SeatType;
  enabled: boolean;
  priceMonthly: string;
  priceDaily: string;
  capacity: string;
}

interface FormState {
  name: string;
  description: string;
  phone: string;
  address: string;
  city: string;
  latitude: string;
  longitude: string;
  amenities: string[];
  hours: Record<string, DayHours>;
  seatTypes: SeatTypeRow[];
}

/**
 * Build one editable row per backend seat type, seeded from the workspace's
 * saved rows when present and defaulted (disabled, empty) otherwise.
 */
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

function buildInitial(ws: Workspace): FormState {
  return {
    name: ws.name ?? "",
    description: ws.description ?? "",
    phone: ws.phone ?? "",
    address: ws.address ?? "",
    city: ws.city ?? "",
    latitude: ws.latitude != null ? String(ws.latitude) : "",
    longitude: ws.longitude != null ? String(ws.longitude) : "",
    amenities: ws.amenities ?? [],
    hours: parseHours(ws.working_hours),
    seatTypes: parseSeatTypes(ws.seat_types),
  };
}

/** Tabbed workspace settings editor. One Save persists the whole form. */
export function SettingsTabs({ workspace, locale }: SettingsTabsProps) {
  const t = useTranslations("owner");
  const tm = useTranslations("messaging.settings.owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [tab, setTab] = useState("basic");
  const [form, setForm] = useState<FormState>(() => buildInitial(workspace));
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  const toggleAmenity = (key: string) =>
    setForm((f) => ({
      ...f,
      amenities: f.amenities.includes(key)
        ? f.amenities.filter((a) => a !== key)
        : [...f.amenities, key],
    }));

  const setDay = (day: string, patch: Partial<DayHours>) =>
    setForm((f) => ({
      ...f,
      hours: { ...f.hours, [day]: { ...f.hours[day], ...patch } },
    }));

  const setSeatType = (type: SeatType, patch: Partial<SeatTypeRow>) =>
    setForm((f) => ({
      ...f,
      seatTypes: f.seatTypes.map((row) =>
        row.type === type ? { ...row, ...patch } : row,
      ),
    }));

  const save = () => {
    const payload: WorkspaceSettingsInput = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      phone: form.phone.trim() || null,
      address: form.address.trim(),
      city: form.city.trim(),
      latitude: form.latitude ? Number(form.latitude) : null,
      longitude: form.longitude ? Number(form.longitude) : null,
      amenities: form.amenities,
      working_hours: form.hours,
    };

    startTransition(async () => {
      const res = await updateSettings(payload);
      if (res.ok) {
        toast({ tone: "ok", title: t("settings.saved") });
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("settings.saveFailed"), body: res.message });
      }
    });
  };

  const toNullableNumber = (value: string): number | null => {
    const trimmed = value.trim();
    if (trimmed === "") return null;
    const n = Number(trimmed);
    return Number.isFinite(n) ? n : null;
  };

  const saveSeatTypes = () => {
    const rows: SeatTypeInput[] = form.seatTypes.map((row) => ({
      type: row.type,
      price_monthly: toNullableNumber(row.priceMonthly),
      price_daily: toNullableNumber(row.priceDaily),
      capacity: Math.max(0, Math.trunc(Number(row.capacity) || 0)),
      enabled: row.enabled,
    }));

    startTransition(async () => {
      const res = await updateSeatTypes(rows);
      if (res.ok) {
        setErrors({});
        toast({ tone: "ok", title: t("settings.saved") });
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("settings.saveFailed"), body: res.message });
      }
    });
  };

  const tabs = [
    { id: "basic", label: t("settings.tabBasic") },
    { id: "location", label: t("settings.tabLocation") },
    { id: "seatTypes", label: t("settings.tabSeatTypes") },
    { id: "amenities", label: t("settings.tabAmenities") },
    { id: "photos", label: t("settings.tabPhotos") },
    { id: "hours", label: t("settings.tabHours") },
    { id: "messaging", label: tm("tab") },
  ];

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="between wrap" style={{ gap: 12 }}>
        <Tabs items={tabs} value={tab} onChange={setTab} />
        <div className="row wrap" style={{ gap: 14 }}>
          <div className="row" style={{ gap: 8 }}>
            <span className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("settings.status")}:
            </span>
            <StatusBadge status={workspace.status} locale={locale} />
          </div>
          <div className="row" style={{ gap: 8 }}>
            <span className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("settings.publishStatus")}:
            </span>
            {workspace.is_published ? (
              <Badge tone="success" dot>
                {t("settings.published")}
              </Badge>
            ) : (
              <Badge tone="warning" dot>
                {t("settings.pendingPublish")}
              </Badge>
            )}
          </div>
        </div>
      </div>

      <div className="card card-pad">
        {tab === "basic" && (
          <div className="stack" style={{ gap: 16 }}>
            <Field label={t("settings.name")} error={errors.name?.[0]}>
              <Input value={form.name} onChange={(e) => set("name", e.target.value)} />
            </Field>
            <Field label={t("settings.description")} error={errors.description?.[0]}>
              <Textarea
                rows={4}
                value={form.description}
                onChange={(e) => set("description", e.target.value)}
              />
            </Field>
            <Field label={t("settings.phone")} error={errors.phone?.[0]}>
              <Input
                className="ltr"
                value={form.phone}
                onChange={(e) => set("phone", e.target.value)}
              />
            </Field>
          </div>
        )}

        {tab === "location" && (
          <div className="stack" style={{ gap: 16 }}>
            <Field label={t("settings.address")} error={errors.address?.[0]}>
              <Input
                value={form.address}
                onChange={(e) => set("address", e.target.value)}
              />
            </Field>
            <Field label={t("settings.city")} error={errors.city?.[0]}>
              <Input value={form.city} onChange={(e) => set("city", e.target.value)} />
            </Field>
            <LocationPicker
              lat={Number(form.latitude)}
              lng={Number(form.longitude)}
              label={t("settings.tabLocation")}
              hint={t("settings.mapHint")}
              onChange={(la, ln) => {
                set("latitude", String(la));
                set("longitude", String(ln));
              }}
            />
            <div className="grid2">
              <Field label={t("settings.latitude")} error={errors.latitude?.[0]}>
                <Input
                  className="tnum ltr"
                  inputMode="decimal"
                  value={form.latitude}
                  onChange={(e) => set("latitude", e.target.value)}
                />
              </Field>
              <Field label={t("settings.longitude")} error={errors.longitude?.[0]}>
                <Input
                  className="tnum ltr"
                  inputMode="decimal"
                  value={form.longitude}
                  onChange={(e) => set("longitude", e.target.value)}
                />
              </Field>
            </div>
          </div>
        )}

        {tab === "seatTypes" && (
          <div className="stack" style={{ gap: 14 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("settings.seatTypesHint")}
            </p>
            <div className="stack" style={{ gap: 12 }}>
              {form.seatTypes.map((row) => (
                <div
                  key={row.type}
                  className={`seat-type-card ${row.enabled ? "is-on" : ""}`}
                >
                  <div className="seat-type-head">
                    <Checkbox
                      checked={row.enabled}
                      onChange={(e) =>
                        setSeatType(row.type, { enabled: e.target.checked })
                      }
                    >
                      <span className="hours-day">
                        {t(`settings.seatTypeNames.${row.type}` as never)}
                      </span>
                    </Checkbox>
                  </div>
                  {row.enabled && (
                    <div className="seat-type-fields">
                      <Field label={t("settings.seatMonthlyPrice")}>
                        <Input
                          className="tnum"
                          inputMode="decimal"
                          placeholder="0"
                          value={row.priceMonthly}
                          onChange={(e) =>
                            setSeatType(row.type, { priceMonthly: e.target.value })
                          }
                        />
                      </Field>
                      <Field label={t("settings.seatDailyPrice")}>
                        <Input
                          className="tnum"
                          inputMode="decimal"
                          placeholder="0"
                          value={row.priceDaily}
                          onChange={(e) =>
                            setSeatType(row.type, { priceDaily: e.target.value })
                          }
                        />
                      </Field>
                      <Field label={t("settings.seatCapacity")}>
                        <Input
                          className="tnum"
                          inputMode="numeric"
                          placeholder="0"
                          value={row.capacity}
                          onChange={(e) =>
                            setSeatType(row.type, { capacity: e.target.value })
                          }
                        />
                      </Field>
                    </div>
                  )}
                </div>
              ))}
            </div>
            <Button
              variant="primary"
              icon="check"
              loading={pending}
              onClick={saveSeatTypes}
              style={{ alignSelf: "flex-start" }}
            >
              {pending ? t("settings.saving") : t("settings.save")}
            </Button>
          </div>
        )}

        {tab === "amenities" && (
          <div className="stack" style={{ gap: 14 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("settings.amenitiesHint")}
            </p>
            <div className="amenity-grid">
              {AMENITY_KEYS.map((key) => {
                const on = form.amenities.includes(key);
                return (
                  <button
                    key={key}
                    type="button"
                    className={`amenity-toggle ${on ? "is-on" : ""}`}
                    onClick={() => toggleAmenity(key)}
                    aria-pressed={on}
                  >
                    <span className="check">{on && <Icon name="check" size={13} />}</span>
                    {t(`settings.amenityList.${key}` as never)}
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {tab === "photos" && <PhotoManager photos={workspace.photos ?? []} />}

        {tab === "hours" && (
          <div className="stack" style={{ gap: 12 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("settings.hoursHint")}
            </p>
            <div className="stack" style={{ gap: 8 }}>
              {DAY_KEYS.map((day) => {
                const d = form.hours[day];
                return (
                  <div key={day} className={`hours-row ${d.open ? "is-open" : ""}`}>
                    <Checkbox
                      checked={d.open}
                      onChange={(e) => setDay(day, { open: e.target.checked })}
                    >
                      <span className="hours-day">
                        {t(`settings.days.${day}` as never)}
                      </span>
                    </Checkbox>
                    {d.open ? (
                      <div className="hours-times">
                        <Input
                          type="time"
                          className="tnum ltr"
                          value={d.from}
                          onChange={(e) => setDay(day, { from: e.target.value })}
                        />
                        <span className="muted">{t("settings.to")}</span>
                        <Input
                          type="time"
                          className="tnum ltr"
                          value={d.to}
                          onChange={(e) => setDay(day, { to: e.target.value })}
                        />
                      </div>
                    ) : (
                      <span className="muted-3 hours-closed">{t("settings.closed")}</span>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {tab === "messaging" && (
          <MessagingTab messaging={workspace.messaging} />
        )}
      </div>

      {tab !== "photos" && tab !== "seatTypes" && tab !== "messaging" && (
        <Button
          variant="primary"
          icon="check"
          loading={pending}
          onClick={save}
          style={{ alignSelf: "flex-start" }}
        >
          {pending ? t("settings.saving") : t("settings.save")}
        </Button>
      )}
    </div>
  );
}
