"use client";

import { useEffect, useState, useTransition } from "react";
import dynamic from "next/dynamic";
import { useLocale, useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Badge, StatusBadge } from "@/components/ui/Badge";
import { Tabs } from "@/components/ui/Tabs";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { updateSettings, type WorkspaceSettingsInput } from "@/lib/actions/owner";
import type { City, Workspace } from "@/lib/types";
import type { ApprovalRequestEntry } from "@/lib/types/approvals";
import { PhotoManager } from "./PhotoManager";
import { LogoManager } from "./LogoManager";
import { MessagingTab } from "./MessagingTab";
import { DomainTab } from "./DomainTab";
import { OperationsTab } from "./OperationsTab";

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

export interface SettingsTabsProps {
  workspace: Workspace;
  locale: string;
  /** The owner's operations requests still awaiting a decision. */
  pendingApprovals?: ApprovalRequestEntry[];
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

/** Mirrors the backend WorkspaceTimezone / WorkspaceCurrency enums. */
const TIMEZONES = [
  "Asia/Gaza",
  "Asia/Jerusalem",
  "Asia/Amman",
  "Asia/Beirut",
  "Asia/Damascus",
  "Asia/Baghdad",
  "Asia/Riyadh",
  "Asia/Kuwait",
  "Asia/Qatar",
  "Asia/Dubai",
  "Africa/Cairo",
  "Africa/Tripoli",
  "Africa/Tunis",
  "Africa/Algiers",
  "Africa/Casablanca",
  "UTC",
] as const;

const CURRENCIES = ["ILS", "USD", "EUR", "JOD", "EGP", "SAR", "AED", "KWD"] as const;

interface FormState {
  name: string;
  description: string;
  phone: string;
  contactEmail: string;
  bankName: string;
  iban: string;
  timezone: string;
  currency: string;
  wifiSsid: string;
  wifiPassword: string;
  address: string;
  cityId: string;
  latitude: string;
  longitude: string;
  amenities: string[];
}

function buildInitial(ws: Workspace): FormState {
  return {
    name: ws.name ?? "",
    description: ws.description ?? "",
    phone: ws.phone ?? "",
    contactEmail: ws.contact_email ?? "",
    bankName: ws.bank_name ?? "",
    iban: ws.iban ?? "",
    timezone: ws.timezone ?? TIMEZONES[0],
    currency: ws.currency ?? CURRENCIES[0],
    wifiSsid: ws.wifi_ssid ?? "",
    wifiPassword: ws.wifi_password ?? "",
    address: ws.address ?? "",
    cityId: ws.city_id ?? "",
    latitude: ws.latitude != null ? String(ws.latitude) : "",
    longitude: ws.longitude != null ? String(ws.longitude) : "",
    amenities: ws.amenities ?? [],
  };
}

/** Tabbed workspace settings editor. One Save persists the whole form. */
export function SettingsTabs({ workspace, locale, pendingApprovals = [] }: SettingsTabsProps) {
  const t = useTranslations("owner");
  const tm = useTranslations("messaging.settings.owner");
  const localeTag = useLocale();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  // Active cities for the dynamic city select; fetched client-side from the
  // public endpoint. Seeded against the workspace's current `city_id`.
  const [cities, setCities] = useState<City[]>([]);
  const cityName = (c: City) => (localeTag === "ar" ? c.name_ar : c.name_en);

  useEffect(() => {
    let active = true;
    fetch(`${API_BASE.replace(/\/$/, "")}/cities`, {
      headers: { Accept: "application/json" },
    })
      .then((res) => (res.ok ? res.json() : { data: [] }))
      .then((body: { data?: City[] }) => {
        if (active) setCities(body.data ?? []);
      })
      .catch(() => {
        if (active) setCities([]);
      });
    return () => {
      active = false;
    };
  }, []);

  const [tab, setTab] = useState("basic");
  const [form, setForm] = useState<FormState>(() => buildInitial(workspace));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  // Draft text for a free-text amenity not in the preset list.
  const [customAmenity, setCustomAmenity] = useState("");

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  const toggleAmenity = (key: string) =>
    setForm((f) => ({
      ...f,
      amenities: f.amenities.includes(key)
        ? f.amenities.filter((a) => a !== key)
        : [...f.amenities, key],
    }));

  // Append a custom amenity (deduped, length-capped to the backend's max:60).
  const addCustomAmenity = () => {
    const value = customAmenity.trim().slice(0, 60);
    if (value === "") return;
    setForm((f) =>
      f.amenities.includes(value)
        ? f
        : { ...f, amenities: [...f.amenities, value] },
    );
    setCustomAmenity("");
  };

  // Amenities the owner typed themselves (everything outside the preset grid).
  const presetAmenities = new Set<string>(AMENITY_KEYS);
  const customAmenities = form.amenities.filter((a) => !presetAmenities.has(a));

  const save = () => {
    const payload: WorkspaceSettingsInput = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      phone: form.phone.trim() || null,
      contact_email: form.contactEmail.trim() || null,
      bank_name: form.bankName.trim() || null,
      iban: form.iban.trim() || null,
      timezone: form.timezone,
      currency: form.currency,
      wifi_ssid: form.wifiSsid.trim() || null,
      wifi_password: form.wifiPassword || null,
      address: form.address.trim(),
      city_id: form.cityId,
      latitude: form.latitude ? Number(form.latitude) : null,
      longitude: form.longitude ? Number(form.longitude) : null,
      amenities: form.amenities,
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
    { id: "general", label: t("settings.tabGeneral") },
    { id: "location", label: t("settings.tabLocation") },
    { id: "amenities", label: t("settings.tabAmenities") },
    { id: "photos", label: t("settings.tabPhotos") },
    { id: "operations", label: t("settings.tabOperations") },
    { id: "messaging", label: tm("tab") },
    { id: "domain", label: t("settings.tabDomain") },
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

        {tab === "general" && (
          <div className="stack" style={{ gap: 16 }}>
            <Field label={t("settings.contactEmail")} error={errors.contact_email?.[0]}>
              <Input
                className="ltr"
                type="email"
                value={form.contactEmail}
                onChange={(e) => set("contactEmail", e.target.value)}
              />
            </Field>
            <Field label={t("settings.bankName")} error={errors.bank_name?.[0]}>
              <Input value={form.bankName} onChange={(e) => set("bankName", e.target.value)} />
            </Field>
            <Field label={t("settings.iban")} error={errors.iban?.[0]}>
              <Input
                className="ltr"
                value={form.iban}
                onChange={(e) => set("iban", e.target.value)}
              />
            </Field>
            <Field label={t("settings.timezone")} error={errors.timezone?.[0]}>
              <Select value={form.timezone} onChange={(e) => set("timezone", e.target.value)}>
                {TIMEZONES.map((tz) => (
                  <option key={tz} value={tz}>
                    {tz}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("settings.currency")} error={errors.currency?.[0]}>
              <Select value={form.currency} onChange={(e) => set("currency", e.target.value)}>
                {CURRENCIES.map((code) => (
                  <option key={code} value={code}>
                    {code}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("settings.wifiSsid")} error={errors.wifi_ssid?.[0]}>
              <Input
                className="ltr"
                value={form.wifiSsid}
                onChange={(e) => set("wifiSsid", e.target.value)}
              />
            </Field>
            <Field label={t("settings.wifiPassword")} error={errors.wifi_password?.[0]}>
              <Input
                className="ltr"
                type="password"
                autoComplete="new-password"
                value={form.wifiPassword}
                onChange={(e) => set("wifiPassword", e.target.value)}
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
            <Field
              label={t("settings.city")}
              error={errors.city_id?.[0] ?? errors.city?.[0]}
            >
              <Select
                value={form.cityId}
                onChange={(e) => set("cityId", e.target.value)}
              >
                <option value="" disabled>
                  {t("settings.cityPlaceholder")}
                </option>
                {/* Keep the saved city visible until the list resolves (or if it
                    became inactive and is absent from the active list). */}
                {form.cityId &&
                  !cities.some((c) => c.id === form.cityId) && (
                    <option value={form.cityId}>{workspace.city}</option>
                  )}
                {cities.map((c) => (
                  <option key={c.id} value={c.id}>
                    {cityName(c)}
                  </option>
                ))}
              </Select>
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

            <div className="stack" style={{ gap: 8 }}>
              <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
                {t("settings.customAmenitiesHint")}
              </p>
              <div className="row" style={{ gap: 8 }}>
                <Input
                  value={customAmenity}
                  onChange={(e) => setCustomAmenity(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault();
                      addCustomAmenity();
                    }
                  }}
                  maxLength={60}
                  placeholder={t("settings.customAmenityPlaceholder")}
                />
                <Button
                  type="button"
                  variant="secondary"
                  icon="plus"
                  onClick={addCustomAmenity}
                  disabled={customAmenity.trim() === ""}
                >
                  {t("settings.customAmenityAdd")}
                </Button>
              </div>
              {customAmenities.length > 0 && (
                <div className="row wrap" style={{ gap: 8 }}>
                  {customAmenities.map((a) => (
                    <span key={a} className="chip">
                      {a}
                      <button
                        type="button"
                        onClick={() => toggleAmenity(a)}
                        aria-label={t("settings.removeAmenity")}
                      >
                        <Icon name="x" size={12} />
                      </button>
                    </span>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {tab === "photos" && (
          <div className="stack" style={{ gap: 28 }}>
            <div className="stack" style={{ gap: 10 }}>
              <h4 className="h4">{t("settings.logoTitle")}</h4>
              <LogoManager
                logo={workspace.logo ?? null}
                hasLogo={workspace.has_logo ?? false}
              />
            </div>
            <div className="stack" style={{ gap: 10 }}>
              <h4 className="h4">{t("settings.photosTitle")}</h4>
              <PhotoManager photos={workspace.photos ?? []} />
            </div>
          </div>
        )}

        {tab === "operations" && (
          <OperationsTab workspace={workspace} pending={pendingApprovals} />
        )}

        {tab === "messaging" && (
          <MessagingTab messaging={workspace.messaging} />
        )}

        {tab === "domain" && workspace.slug && workspace.urls && (
          <DomainTab
            slug={workspace.slug}
            code={workspace.code ?? ""}
            dashboardUrl={workspace.urls.dashboard}
            publicUrl={workspace.urls.public}
          />
        )}
      </div>

      {tab !== "photos" && tab !== "operations" && tab !== "messaging" && tab !== "domain" && (
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
