"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { StatusBadge } from "@/components/ui/Badge";
import { Tabs } from "@/components/ui/Tabs";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { updateSettings, type WorkspaceSettingsInput } from "@/lib/actions/owner";
import type { Workspace } from "@/lib/types";
import { PhotoManager } from "./PhotoManager";

export interface SettingsTabsProps {
  workspace: Workspace;
  locale: string;
}

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

interface DayHours {
  open: boolean;
  from: string;
  to: string;
}

interface FormState {
  name: string;
  description: string;
  phone: string;
  address: string;
  city: string;
  latitude: string;
  longitude: string;
  totalSeats: string;
  pricePerMonth: string;
  amenities: string[];
  hours: Record<string, DayHours>;
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
    totalSeats: ws.total_seats != null ? String(ws.total_seats) : "",
    pricePerMonth: ws.price_per_month != null ? String(ws.price_per_month) : "",
    amenities: ws.amenities ?? [],
    hours: parseHours(ws.working_hours),
  };
}

/** Tabbed workspace settings editor. One Save persists the whole form. */
export function SettingsTabs({ workspace, locale }: SettingsTabsProps) {
  const t = useTranslations("owner");
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

  const save = () => {
    const payload: WorkspaceSettingsInput = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      phone: form.phone.trim() || null,
      address: form.address.trim(),
      city: form.city.trim(),
      latitude: form.latitude ? Number(form.latitude) : null,
      longitude: form.longitude ? Number(form.longitude) : null,
      total_seats: Number(form.totalSeats) || undefined,
      price_per_month: Number(form.pricePerMonth) || undefined,
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

  const tabs = [
    { id: "basic", label: t("settings.tabBasic") },
    { id: "location", label: t("settings.tabLocation") },
    { id: "pricing", label: t("settings.tabPricing") },
    { id: "amenities", label: t("settings.tabAmenities") },
    { id: "photos", label: t("settings.tabPhotos") },
    { id: "hours", label: t("settings.tabHours") },
  ];

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="between wrap" style={{ gap: 12 }}>
        <Tabs items={tabs} value={tab} onChange={setTab} />
        <div className="row" style={{ gap: 8 }}>
          <span className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("settings.status")}:
          </span>
          <StatusBadge status={workspace.status} locale={locale} />
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
            <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
              {t("settings.mapHint")}
            </p>
          </div>
        )}

        {tab === "pricing" && (
          <div className="grid2">
            <Field label={t("settings.totalSeats")} error={errors.total_seats?.[0]}>
              <Input
                className="tnum"
                inputMode="numeric"
                value={form.totalSeats}
                onChange={(e) => set("totalSeats", e.target.value)}
              />
            </Field>
            <Field
              label={t("settings.pricePerMonth")}
              error={errors.price_per_month?.[0]}
            >
              <Input
                className="tnum"
                inputMode="decimal"
                value={form.pricePerMonth}
                onChange={(e) => set("pricePerMonth", e.target.value)}
              />
            </Field>
          </div>
        )}

        {tab === "amenities" && (
          <div className="stack" style={{ gap: 14 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("settings.amenitiesHint")}
            </p>
            <div className="amenity-grid">
              {AMENITY_KEYS.map((key) => (
                <Checkbox
                  key={key}
                  checked={form.amenities.includes(key)}
                  onChange={() => toggleAmenity(key)}
                >
                  {t(`settings.amenityList.${key}` as never)}
                </Checkbox>
              ))}
            </div>
          </div>
        )}

        {tab === "photos" && <PhotoManager photos={workspace.photos ?? []} />}

        {tab === "hours" && (
          <div className="stack" style={{ gap: 12 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("settings.hoursHint")}
            </p>
            {DAY_KEYS.map((day) => {
              const d = form.hours[day];
              return (
                <div key={day} className="between wrap" style={{ gap: 12 }}>
                  <Checkbox
                    checked={d.open}
                    onChange={(e) => setDay(day, { open: e.target.checked })}
                  >
                    <span style={{ minWidth: 80, display: "inline-block" }}>
                      {t(`settings.days.${day}` as never)}
                    </span>
                  </Checkbox>
                  {d.open ? (
                    <div className="row" style={{ gap: 8 }}>
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
                    <span className="muted-3">{t("settings.closed")}</span>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>

      {tab !== "photos" && (
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
