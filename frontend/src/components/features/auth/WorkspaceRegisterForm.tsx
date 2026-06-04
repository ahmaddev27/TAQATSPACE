"use client";

import { useState } from "react";
import dynamic from "next/dynamic";
import { useForm, useWatch, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Stepper } from "@/components/ui/Stepper";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Textarea } from "@/components/ui/Textarea";
import { Checkbox } from "@/components/ui/Checkbox";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { FileDropzone } from "@/components/ui/FileDropzone";
import { Icon, type IconName } from "@/components/ui/Icon";
import {
  workspaceRegisterSchema,
  WORKSPACE_STEP_FIELDS,
  AMENITY_CODES,
  SEAT_TYPE_CODES,
  type WorkspaceRegisterValues,
} from "@/lib/validations/auth";
import { PendingReviewScreen } from "./PendingReviewScreen";

// Default to Gaza centre until the owner picks an exact point on the map.
const GAZA_CENTER = { lat: 31.5, lng: 34.47 };

// Mapbox GL touches `window`, so load the picker on the client only.
const LocationPicker = dynamic(
  () => import("@/components/features/public/LocationPicker").then((m) => m.LocationPicker),
  {
    ssr: false,
    loading: () => <div className="map-canvas"><div className="map-fallback" style={{ height: 320 }} /></div>,
  },
);

const CITY_KEYS = ["gaza", "khanyounis", "rafah", "jabalia", "deirelbalah"] as const;

const AMENITY_ICONS: Record<string, IconName> = {
  wifi: "wifi",
  printer: "printer",
  meeting_room: "users",
  parking: "parking",
  coffee: "coffee",
  kitchen: "coffee",
  snow: "snow",
};

function stepForField(field: string): number {
  const idx = WORKSPACE_STEP_FIELDS.findIndex((fields) =>
    (fields as string[]).includes(field),
  );
  return idx === -1 ? 0 : idx;
}

export function WorkspaceRegisterForm() {
  const t = useTranslations("auth.registerWorkspace");
  const tv = useTranslations("validation");
  const tCommon = useTranslations("common");

  const [step, setStep] = useState(0);
  const [done, setDone] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  const steps = [
    t("steps.account"),
    t("steps.info"),
    t("steps.location"),
    t("steps.seats"),
    t("steps.documents"),
  ];

  const {
    register,
    handleSubmit,
    trigger,
    control,
    setValue,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<WorkspaceRegisterValues>({
    resolver: zodResolver(workspaceRegisterSchema(tv)),
    mode: "onTouched",
    defaultValues: {
      name: "",
      email: "",
      phone: "",
      password: "",
      password_confirmation: "",
      workspace_name: "",
      description: "",
      capacity: 1,
      hours: "",
      city: CITY_KEYS[0],
      area: "",
      address: "",
      lat: GAZA_CENTER.lat,
      lng: GAZA_CENTER.lng,
      seat_types: SEAT_TYPE_CODES.map((type, i) => ({
        type,
        enabled: i === 0, // Flexible on by default; owner toggles the rest.
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

  function toggleAmenity(code: string) {
    const set = new Set(amenities);
    if (set.has(code)) set.delete(code);
    else set.add(code);
    setValue("amenities", Array.from(set), { shouldValidate: true });
  }

  async function goNext() {
    const fieldsToValidate = WORKSPACE_STEP_FIELDS[step];
    const valid = await trigger(fieldsToValidate);
    if (valid) setStep((s) => Math.min(s + 1, steps.length - 1));
  }

  async function onSubmit(values: WorkspaceRegisterValues) {
    setServerError(null);
    const fd = new FormData();
    fd.append("role", "workspace_owner");
    // Owner account.
    fd.append("name", values.name);
    fd.append("email", values.email);
    fd.append("phone", values.phone);
    fd.append("password", values.password);
    fd.append("password_confirmation", values.password_confirmation);
    // Workspace profile.
    fd.append("workspace_name", values.workspace_name);
    fd.append("description", values.description);
    fd.append("capacity", String(values.capacity));
    fd.append("hours", values.hours);
    fd.append("city", values.city);
    fd.append("area", values.area);
    fd.append("address", values.address);
    fd.append("lat", String(values.lat));
    fd.append("lng", String(values.lng));
    // Only submit the seat types the owner enabled (matches backend contract).
    const num = (v: string): string => (v.trim() === "" ? "" : String(Number(v)));
    values.seat_types
      .filter((s) => s.enabled)
      .forEach((s, i) => {
        fd.append(`seat_types[${i}][type]`, s.type);
        fd.append(`seat_types[${i}][enabled]`, "1");
        fd.append(
          `seat_types[${i}][capacity]`,
          s.capacity.trim() === "" ? "0" : String(Math.trunc(Number(s.capacity))),
        );
        const monthly = num(s.price_monthly);
        const daily = num(s.price_daily);
        if (monthly !== "") fd.append(`seat_types[${i}][price_monthly]`, monthly);
        if (daily !== "") fd.append(`seat_types[${i}][price_daily]`, daily);
      });
    (values.amenities ?? []).forEach((a, i) => fd.append(`amenities[${i}]`, a));
    if (values.license_file) fd.append("license_file", values.license_file);
    if (values.id_document) fd.append("id_document", values.id_document);

    try {
      const res = await fetch("/api/auth/register", {
        method: "POST",
        body: fd,
        credentials: "include",
      });

      if (res.status === 201) {
        // Workspace owners are NOT logged in — go to pending review.
        setDone(true);
        return;
      }

      const body = (await res.json().catch(() => ({}))) as {
        message?: string;
        errors?: Record<string, string[]>;
      };

      if (res.status === 422 && body.errors) {
        let firstStep = steps.length - 1;
        for (const [field, messages] of Object.entries(body.errors)) {
          const root = field.split(".")[0].split("[")[0];
          setError(root as keyof WorkspaceRegisterValues, {
            message: messages[0],
          });
          firstStep = Math.min(firstStep, stepForField(root));
        }
        setStep(firstStep);
      } else {
        setServerError(body.message ?? "Registration failed.");
      }
    } catch {
      setServerError("Unable to reach the server.");
    }
  }

  if (done) return <PendingReviewScreen />;

  return (
    <div className="reg-wrap">
      <div style={{ textAlign: "center", marginBottom: 28 }}>
        <span className="eyebrow">{t("eyebrow")}</span>
        <h1 className="h1" style={{ marginTop: 8 }}>
          {t("title")}
        </h1>
      </div>

      <Stepper steps={steps} current={step} />

      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <div className="card reg-card">
          {serverError && (
            <div style={{ marginBottom: 16 }}>
              <Alert tone="danger" title={serverError} />
            </div>
          )}

          {step === 0 && (
            <div className="stack" style={{ gap: 16 }}>
              <Field label={t("ownerName")} error={errors.name?.message}>
                <Input
                  placeholder={t("ownerNamePlaceholder")}
                  {...register("name")}
                />
              </Field>
              <Field label={t("email")} error={errors.email?.message}>
                <Input
                  icon="mail"
                  type="email"
                  placeholder="name@mail.ps"
                  {...register("email")}
                />
              </Field>
              <Field
                label={t("phone")}
                hint={t("phoneHint")}
                error={errors.phone?.message}
              >
                <Input
                  icon="phone"
                  className="ltr"
                  placeholder={t("phonePlaceholder")}
                  {...register("phone")}
                />
              </Field>
              <div className="grid2">
                <Field label={t("password")} error={errors.password?.message}>
                  <Input icon="lock" type="password" {...register("password")} />
                </Field>
                <Field
                  label={t("passwordConfirm")}
                  error={errors.password_confirmation?.message}
                >
                  <Input
                    icon="lock"
                    type="password"
                    {...register("password_confirmation")}
                  />
                </Field>
              </div>
            </div>
          )}

          {step === 1 && (
            <div className="stack" style={{ gap: 16 }}>
              <Field label={t("name")} error={errors.workspace_name?.message}>
                <Input
                  placeholder={t("namePlaceholder")}
                  {...register("workspace_name")}
                />
              </Field>
              <Field label={t("description")} error={errors.description?.message}>
                <Textarea
                  placeholder={t("descriptionPlaceholder")}
                  {...register("description")}
                />
              </Field>
              <div className="grid2">
                <Field label={t("capacity")} error={errors.capacity?.message}>
                  <Input
                    className="ltr"
                    type="number"
                    min={1}
                    placeholder="48"
                    {...register("capacity", { valueAsNumber: true })}
                  />
                </Field>
                <Field label={t("hours")} error={errors.hours?.message}>
                  <Input placeholder={t("hoursPlaceholder")} {...register("hours")} />
                </Field>
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="stack" style={{ gap: 16 }}>
              <div className="grid2">
                <Field label={t("city")} error={errors.city?.message}>
                  <Select {...register("city")}>
                    {CITY_KEYS.map((key) => (
                      <option key={key} value={key}>
                        {tCommon(`city.${key}`)}
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field label={t("area")} error={errors.area?.message}>
                  <Input placeholder={t("areaPlaceholder")} {...register("area")} />
                </Field>
              </div>
              <Field label={t("address")} error={errors.address?.message}>
                <Input
                  icon="pin"
                  placeholder={t("addressPlaceholder")}
                  {...register("address")}
                />
              </Field>

              <LocationPicker
                lat={lat}
                lng={lng}
                label={t("mapLabel")}
                hint={t("mapStubNote")}
                onChange={(nextLat, nextLng) => {
                  setValue("lat", nextLat, { shouldValidate: true });
                  setValue("lng", nextLng, { shouldValidate: true });
                }}
              />
              <input type="hidden" {...register("lat", { valueAsNumber: true })} />
              <input type="hidden" {...register("lng", { valueAsNumber: true })} />
            </div>
          )}

          {step === 3 && (
            <div className="stack" style={{ gap: 16 }}>
              <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
                {t("seatsIntro")}
              </p>

              {SEAT_TYPE_CODES.map((type, i) => {
                const on = seatTypes?.[i]?.enabled ?? false;
                return (
                  <div
                    key={type}
                    className={`seat-type-card ${on ? "is-on" : ""}`}
                  >
                    <div className="seat-type-head">
                      <Checkbox
                        checked={on}
                        onChange={(e) =>
                          setValue(`seat_types.${i}.enabled`, e.target.checked, {
                            shouldValidate: true,
                          })
                        }
                      >
                        <span className="hours-day">
                          {t(`seatTypeNames.${type}`)}
                        </span>
                      </Checkbox>
                    </div>
                    {on && (
                      <div className="seat-type-fields">
                        <Field
                          label={t("seatMonthlyPrice")}
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
                          label={t("seatDailyPrice")}
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
                          label={t("seatCapacity")}
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
                  {t("amenities")}
                </label>
                <div className="amenity-grid">
                  {AMENITY_CODES.map((code) => {
                    const on = amenities.includes(code);
                    return (
                      <label
                        key={code}
                        className={`amenity-check ${on ? "is-on" : ""}`}
                      >
                        <input
                          type="checkbox"
                          checked={on}
                          onChange={() => toggleAmenity(code)}
                        />
                        <Icon name={AMENITY_ICONS[code]} size={18} />
                        {t(`amenityCodes.${code}`)}
                      </label>
                    );
                  })}
                </div>
              </div>
            </div>
          )}

          {step === 4 && (
            <div className="stack" style={{ gap: 16 }}>
              <div
                className="card"
                style={{
                  padding: "14px 16px",
                  background: "var(--info-bg)",
                  borderColor: "transparent",
                }}
              >
                <div className="row" style={{ gap: 10, color: "var(--blue-700)" }}>
                  <Icon name="shield" size={18} />
                  <span style={{ fontSize: "var(--fs-sm)", fontWeight: 500 }}>
                    {t("docsNote")}
                  </span>
                </div>
              </div>

              <Field label={t("licenseLabel")} error={errors.license_file?.message}>
                <Controller
                  control={control}
                  name="license_file"
                  render={({ field }) => (
                    <FileDropzone
                      value={field.value ?? null}
                      onChange={field.onChange}
                      icon="doc"
                      label={t("licenseDrop")}
                      hint={t("licenseHint")}
                      clearLabel={tCommon("cancel")}
                    />
                  )}
                />
              </Field>

              <Field label={t("ownerIdLabel")} error={errors.id_document?.message}>
                <Controller
                  control={control}
                  name="id_document"
                  render={({ field }) => (
                    <FileDropzone
                      value={field.value ?? null}
                      onChange={field.onChange}
                      icon="user"
                      label={t("ownerIdDrop")}
                      clearLabel={tCommon("cancel")}
                    />
                  )}
                />
              </Field>

              <Field error={errors.terms?.message}>
                <Controller
                  control={control}
                  name="terms"
                  render={({ field }) => (
                    <Checkbox
                      checked={!!field.value}
                      onChange={(e) => field.onChange(e.target.checked)}
                    >
                      {t("terms")}
                      <Link className="link" href="/">
                        {t("termsLink")}
                      </Link>
                    </Checkbox>
                  )}
                />
              </Field>
            </div>
          )}
        </div>

        <div className="between" style={{ marginTop: 24 }}>
          <Button
            type="button"
            variant="ghost"
            icon="arrowL"
            onClick={() => setStep((s) => Math.max(s - 1, 0))}
            disabled={step === 0}
          >
            {tCommon("back")}
          </Button>

          {step < steps.length - 1 ? (
            <Button type="button" variant="primary" iconEnd="arrowR" onClick={goNext}>
              {t("next")}
            </Button>
          ) : (
            <Button
              type="submit"
              variant="primary"
              iconEnd="check"
              loading={isSubmitting}
            >
              {t("submit")}
            </Button>
          )}
        </div>
      </form>
    </div>
  );
}
