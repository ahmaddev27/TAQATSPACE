"use client";

import { useEffect, useState } from "react";
import dynamic from "next/dynamic";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useLocale, useTranslations } from "next-intl";
import { Stepper } from "@/components/ui/Stepper";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Textarea } from "@/components/ui/Textarea";
import { Checkbox } from "@/components/ui/Checkbox";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { Icon, type IconName } from "@/components/ui/Icon";
import {
  ownerOnboardingSchema,
  AMENITY_CODES,
  GENDER_OPTIONS,
  SEAT_TYPE_CODES,
  type OwnerOnboardingValues,
} from "@/lib/validations/auth";
import type { City } from "@/lib/types";
import type { OnboardingResult } from "./OnboardingFlow";
import { OwnerSeatSetup } from "./OwnerSeatSetup";

const GAZA_CENTER = { lat: 31.5, lng: 34.47 };

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

const LocationPicker = dynamic(
  () =>
    import("@/components/features/public/LocationPicker").then((m) => m.LocationPicker),
  {
    ssr: false,
    loading: () => (
      <div className="map-canvas">
        <div className="map-fallback" style={{ height: 320 }} />
      </div>
    ),
  },
);

const AMENITY_ICONS: Record<string, IconName> = {
  wifi: "wifi",
  printer: "printer",
  meeting_room: "users",
  parking: "parking",
  coffee: "coffee",
  kitchen: "coffee",
  snow: "snow",
};

/** Field groups per step — scopes RHF `trigger()` and back-navigation on errors. */
const STEP_FIELDS: (keyof OwnerOnboardingValues)[][] = [
  ["phone", "gender", "workspace_name", "description", "capacity", "hours"],
  ["city_id", "area", "address"],
  ["seat_types", "amenities"],
];

function stepForField(field: string): number {
  const idx = STEP_FIELDS.findIndex((fields) => (fields as string[]).includes(field));
  return idx === -1 ? 0 : idx;
}

/**
 * Workspace-owner branch of SSO onboarding: collects the minimal space profile
 * (no account fields, no document uploads), creates the pending workspace, and
 * routes to the pending-review screen via the parent.
 */
export function OwnerOnboardingForm({
  defaultPhone = "",
  onBack,
  onDone,
}: {
  defaultPhone?: string;
  onBack: () => void;
  onDone: (result: OnboardingResult) => void;
}) {
  const t = useTranslations("auth.onboarding");
  const tw = useTranslations("auth.registerWorkspace");
  const tf = useTranslations("auth.registerFreelancer");
  const tv = useTranslations("validation");
  const tCommon = useTranslations("common");
  const tg = useTranslations("common.gender");
  const locale = useLocale();

  // Active cities for the dynamic location select. Fetched client-side from the
  // public (unauthenticated) endpoint; the select shows the locale name and the
  // form submits the chosen city's id.
  const [cities, setCities] = useState<City[]>([]);
  const cityName = (c: City) => (locale === "ar" ? c.name_ar : c.name_en);

  const [step, setStep] = useState(0);
  const [serverError, setServerError] = useState<string | null>(null);
  // After the workspace is created we move to an interactive seat-setup phase
  // (still pending), then hand off to the parent's pending-review screen.
  const [phase, setPhase] = useState<"form" | "seats">("form");
  const [pendingResult, setPendingResult] = useState<OnboardingResult | null>(null);
  const [seatSetupTypes, setSeatSetupTypes] = useState<
    Array<{ type: string; count: number }>
  >([]);

  const steps = [tw("steps.info"), tw("steps.location"), tw("steps.seats")];

  const {
    register,
    handleSubmit,
    trigger,
    control,
    setValue,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<OwnerOnboardingValues>({
    resolver: zodResolver(ownerOnboardingSchema(tv)),
    mode: "onTouched",
    defaultValues: {
      phone: defaultPhone,
      gender: "",
      workspace_name: "",
      description: "",
      capacity: 1,
      hours: "",
      city_id: "",
      area: "",
      address: "",
      lat: GAZA_CENTER.lat,
      lng: GAZA_CENTER.lng,
      seat_types: SEAT_TYPE_CODES.map((type, i) => ({
        type,
        enabled: i === 0,
        price_monthly: "",
        price_daily: "",
        capacity: "",
      })),
      amenities: [],
    },
  });

  const seatTypes = useWatch({ control, name: "seat_types" });
  const amenities = useWatch({ control, name: "amenities" }) ?? [];
  const lat = useWatch({ control, name: "lat" }) ?? GAZA_CENTER.lat;
  const lng = useWatch({ control, name: "lng" }) ?? GAZA_CENTER.lng;

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

  function toggleAmenity(code: string) {
    const set = new Set(amenities);
    if (set.has(code)) set.delete(code);
    else set.add(code);
    setValue("amenities", Array.from(set), { shouldValidate: true });
  }

  async function goNext() {
    const valid = await trigger(STEP_FIELDS[step]);
    if (valid) setStep((s) => Math.min(s + 1, steps.length - 1));
  }

  /**
   * Block Enter from implicitly submitting the form. On the last step (Seats &
   * pricing) the submit button is in the DOM, so pressing Enter while typing a
   * price would submit early — with capacity still empty — skipping straight to
   * the seat-setup screen with 0 seats. Submission must be a deliberate click.
   * (Textarea keeps Enter for newlines.)
   */
  function blockEnterSubmit(e: React.KeyboardEvent<HTMLFormElement>) {
    const target = e.target as HTMLElement;
    if (e.key === "Enter" && target.tagName !== "TEXTAREA") {
      e.preventDefault();
    }
  }

  async function onSubmit(values: OwnerOnboardingValues) {
    setServerError(null);

    const num = (v: string): number | undefined =>
      v.trim() === "" ? undefined : Number(v);

    const payload = {
      role: "workspace_owner" as const,
      phone: values.phone,
      // Empty select = "prefer not to say" → null (unspecified) for the API.
      gender: values.gender === "" ? null : values.gender,
      workspace_name: values.workspace_name,
      description: values.description,
      capacity: values.capacity,
      hours: values.hours,
      city_id: values.city_id,
      area: values.area,
      address: values.address,
      lat: values.lat,
      lng: values.lng,
      amenities: values.amenities ?? [],
      seat_types: values.seat_types
        .filter((s) => s.enabled)
        .map((s) => {
          const row: Record<string, unknown> = {
            type: s.type,
            enabled: true,
            capacity: s.capacity.trim() === "" ? 0 : Math.trunc(Number(s.capacity)),
          };
          const monthly = num(s.price_monthly);
          const daily = num(s.price_daily);
          if (monthly !== undefined) row.price_monthly = monthly;
          if (daily !== undefined) row.price_daily = daily;
          return row;
        }),
    };

    try {
      const res = await fetch("/api/auth/onboarding", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(payload),
      });

      const body = (await res.json().catch(() => ({}))) as Partial<OnboardingResult> & {
        message?: string;
        errors?: Record<string, string[]>;
      };

      if (res.ok && body.role) {
        // Workspace created — go set up its seats (from the enabled types'
        // capacities) before the pending-review screen. With no enabled type
        // there's nothing to lay out, so hand off directly.
        const enabled = values.seat_types
          .filter((s) => s.enabled)
          .map((s) => ({
            type: s.type,
            count: s.capacity.trim() === "" ? 0 : Math.trunc(Number(s.capacity)),
          }));

        if (enabled.length > 0) {
          setPendingResult(body as OnboardingResult);
          setSeatSetupTypes(enabled);
          setPhase("seats");
        } else {
          onDone(body as OnboardingResult);
        }
        return;
      }

      if (res.status === 422 && body.errors) {
        let firstStep = steps.length - 1;
        for (const [field, messages] of Object.entries(body.errors)) {
          const root = field.split(".")[0].split("[")[0];
          setError(root as keyof OwnerOnboardingValues, { message: messages[0] });
          firstStep = Math.min(firstStep, stepForField(root));
        }
        setStep(firstStep);
      } else {
        setServerError(body.message ?? t("error"));
      }
    } catch {
      setServerError(t("error"));
    }
  }

  if (phase === "seats" && pendingResult) {
    return (
      <OwnerSeatSetup
        types={seatSetupTypes}
        onDone={() => onDone(pendingResult)}
      />
    );
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} onKeyDown={blockEnterSubmit} noValidate>
      <Stepper steps={steps} current={step} />

      <div className="card reg-card">
        {serverError && (
          <div style={{ marginBottom: 16 }}>
            <Alert tone="danger" title={serverError} />
          </div>
        )}

        {step === 0 && (
          <div className="stack" style={{ gap: 16 }}>
            <Field label={tf("phone")} hint={tw("phoneHint")} error={errors.phone?.message}>
              <Input
                icon="phone"
                className="ltr"
                placeholder={tw("phonePlaceholder")}
                {...register("phone")}
              />
            </Field>
            <Field label={tg("label")} hint={tg("hint")} error={errors.gender?.message}>
              <Select defaultValue="" {...register("gender")}>
                <option value="">{tg("unspecified")}</option>
                {GENDER_OPTIONS.map((code) => (
                  <option key={code} value={code}>
                    {tg(code)}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={tw("name")} error={errors.workspace_name?.message}>
              <Input placeholder={tw("namePlaceholder")} {...register("workspace_name")} />
            </Field>
            <Field label={tw("description")} error={errors.description?.message}>
              <Textarea
                placeholder={tw("descriptionPlaceholder")}
                {...register("description")}
              />
            </Field>
            <div className="grid2">
              <Field label={tw("capacity")} error={errors.capacity?.message}>
                <Input
                  className="ltr"
                  type="number"
                  min={1}
                  placeholder="48"
                  {...register("capacity", { valueAsNumber: true })}
                />
              </Field>
              <Field label={tw("hours")} error={errors.hours?.message}>
                <Input placeholder={tw("hoursPlaceholder")} {...register("hours")} />
              </Field>
            </div>
          </div>
        )}

        {step === 1 && (
          <div className="stack" style={{ gap: 16 }}>
            <div className="grid2">
              <Field label={tw("city")} error={errors.city_id?.message}>
                <Select defaultValue="" {...register("city_id")}>
                  <option value="" disabled>
                    {tw("cityPlaceholder")}
                  </option>
                  {cities.map((city) => (
                    <option key={city.id} value={city.id}>
                      {cityName(city)}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label={tw("area")} error={errors.area?.message}>
                <Input placeholder={tw("areaPlaceholder")} {...register("area")} />
              </Field>
            </div>
            <Field label={tw("address")} error={errors.address?.message}>
              <Input
                icon="pin"
                placeholder={tw("addressPlaceholder")}
                {...register("address")}
              />
            </Field>

            <LocationPicker
              lat={lat}
              lng={lng}
              label={tw("mapLabel")}
              hint={tw("mapHint")}
              onChange={(nextLat, nextLng) => {
                setValue("lat", nextLat, { shouldValidate: true });
                setValue("lng", nextLng, { shouldValidate: true });
              }}
            />
            <input type="hidden" {...register("lat", { valueAsNumber: true })} />
            <input type="hidden" {...register("lng", { valueAsNumber: true })} />
          </div>
        )}

        {step === 2 && (
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {tw("seatsIntro")}
            </p>

            {SEAT_TYPE_CODES.map((type, i) => {
              const on = seatTypes?.[i]?.enabled ?? false;
              return (
                <div key={type} className={`seat-type-card ${on ? "is-on" : ""}`}>
                  <div className="seat-type-head">
                    <Checkbox
                      checked={on}
                      onChange={(e) =>
                        setValue(`seat_types.${i}.enabled`, e.target.checked, {
                          shouldValidate: true,
                        })
                      }
                    >
                      <span className="hours-day">{tw(`seatTypeNames.${type}`)}</span>
                    </Checkbox>
                  </div>
                  {on && (
                    <div className="seat-type-fields">
                      <Field
                        label={tw("seatMonthlyPrice")}
                        error={errors.seat_types?.[i]?.price_monthly?.message}
                      >
                        <Input
                          className="ltr tnum"
                          type="number"
                          min={0}
                          placeholder="0"
                          {...register(`seat_types.${i}.price_monthly`)}
                        />
                      </Field>
                      <Field
                        label={tw("seatDailyPrice")}
                        error={errors.seat_types?.[i]?.price_daily?.message}
                      >
                        <Input
                          className="ltr tnum"
                          type="number"
                          min={0}
                          placeholder="0"
                          {...register(`seat_types.${i}.price_daily`)}
                        />
                      </Field>
                      <Field
                        label={tw("seatCapacity")}
                        error={errors.seat_types?.[i]?.capacity?.message}
                      >
                        <Input
                          className="ltr tnum"
                          type="number"
                          min={0}
                          placeholder="0"
                          {...register(`seat_types.${i}.capacity`)}
                        />
                      </Field>
                    </div>
                  )}
                  <input
                    type="hidden"
                    {...register(`seat_types.${i}.type`)}
                    value={type}
                  />
                </div>
              );
            })}

            {typeof errors.seat_types?.message === "string" && (
              <span className="hint" style={{ color: "var(--danger)" }}>
                {errors.seat_types.message}
              </span>
            )}

            <div>
              <label className="label" style={{ marginBottom: 8, display: "block" }}>
                {tw("amenities")}
              </label>
              <div className="amenity-grid">
                {AMENITY_CODES.map((code) => {
                  const on = amenities.includes(code);
                  return (
                    <label key={code} className={`amenity-check ${on ? "is-on" : ""}`}>
                      <input
                        type="checkbox"
                        checked={on}
                        onChange={() => toggleAmenity(code)}
                      />
                      <Icon name={AMENITY_ICONS[code]} size={18} />
                      {tw(`amenityCodes.${code}`)}
                    </label>
                  );
                })}
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="between" style={{ marginTop: 24 }}>
        <Button
          type="button"
          variant="ghost"
          icon="arrowL"
          onClick={() => (step === 0 ? onBack() : setStep((s) => Math.max(s - 1, 0)))}
        >
          {tCommon("back")}
        </Button>

        {step < steps.length - 1 ? (
          <Button type="button" variant="primary" iconEnd="arrowR" onClick={goNext}>
            {tw("next")}
          </Button>
        ) : (
          <Button type="submit" variant="primary" iconEnd="check" loading={isSubmitting}>
            {t("submitOwner")}
          </Button>
        )}
      </div>
    </form>
  );
}
