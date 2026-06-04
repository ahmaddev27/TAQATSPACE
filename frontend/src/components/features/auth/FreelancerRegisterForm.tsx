"use client";

import { useState } from "react";
import { useForm, Controller } from "react-hook-form";
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
import { Icon } from "@/components/ui/Icon";
import {
  freelancerRegisterSchema,
  FREELANCER_STEP_FIELDS,
  SPECIALTY_OPTIONS,
  type FreelancerRegisterValues,
} from "@/lib/validations/auth";
import { AccountCreatedScreen } from "./AccountCreatedScreen";

/** Map a backend field error to the step that owns it, so we can route back. */
function stepForField(field: string): number {
  const idx = FREELANCER_STEP_FIELDS.findIndex((fields) =>
    (fields as string[]).includes(field),
  );
  return idx === -1 ? 0 : idx;
}

export function FreelancerRegisterForm() {
  const t = useTranslations("auth.registerFreelancer");
  const tv = useTranslations("validation");
  const tCommon = useTranslations("common");

  const [step, setStep] = useState(0);
  const [done, setDone] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  const steps = [t("steps.personal"), t("steps.specialty"), t("steps.id")];

  const {
    register,
    handleSubmit,
    trigger,
    control,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FreelancerRegisterValues>({
    resolver: zodResolver(freelancerRegisterSchema(tv)),
    mode: "onTouched",
    defaultValues: {
      name: "",
      email: "",
      phone: "",
      password: "",
      password_confirmation: "",
      specialty: "",
      bio: "",
    },
  });

  async function goNext() {
    const fields = FREELANCER_STEP_FIELDS[step];
    const valid = await trigger(fields);
    if (valid) setStep((s) => Math.min(s + 1, steps.length - 1));
  }

  async function onSubmit(values: FreelancerRegisterValues) {
    setServerError(null);
    const fd = new FormData();
    fd.append("role", "freelancer");
    fd.append("name", values.name);
    fd.append("email", values.email);
    fd.append("phone", values.phone);
    fd.append("password", values.password);
    fd.append("password_confirmation", values.password_confirmation);
    fd.append("specialty", values.specialty);
    if (values.bio) fd.append("bio", values.bio);
    if (values.id_document) fd.append("id_document", values.id_document);

    try {
      const res = await fetch("/api/auth/register", {
        method: "POST",
        body: fd,
        credentials: "include",
      });

      if (res.status === 201) {
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
          setError(field as keyof FreelancerRegisterValues, {
            message: messages[0],
          });
          firstStep = Math.min(firstStep, stepForField(field));
        }
        setStep(firstStep);
      } else {
        setServerError(body.message ?? "Registration failed.");
      }
    } catch {
      setServerError("Unable to reach the server.");
    }
  }

  if (done) return <AccountCreatedScreen />;

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
              <Field label={t("name")} error={errors.name?.message}>
                <Input placeholder={t("namePlaceholder")} {...register("name")} />
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
          )}

          {step === 1 && (
            <div className="stack" style={{ gap: 16 }}>
              <Field label={t("specialty")} error={errors.specialty?.message}>
                <Select defaultValue="" {...register("specialty")}>
                  <option value="" disabled>
                    —
                  </option>
                  {SPECIALTY_OPTIONS.map((code) => (
                    <option key={code} value={code}>
                      {t(`specialtyOptions.${code}`)}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field
                label={t("bio")}
                hint={t("bioHint")}
                error={errors.bio?.message}
              >
                <Textarea placeholder={t("bioPlaceholder")} {...register("bio")} />
              </Field>
            </div>
          )}

          {step === 2 && (
            <div className="stack" style={{ gap: 16 }}>
              <div
                className="card"
                style={{
                  padding: "14px 16px",
                  background: "var(--info-bg)",
                  borderColor: "transparent",
                }}
              >
                <div
                  className="row"
                  style={{ gap: 10, color: "var(--blue-700)" }}
                >
                  <Icon name="shield" size={18} />
                  <span style={{ fontSize: "var(--fs-sm)", fontWeight: 500 }}>
                    {t("privacyNote")}
                  </span>
                </div>
              </div>

              <Field label={t("idLabel")} error={errors.id_document?.message}>
                <Controller
                  control={control}
                  name="id_document"
                  render={({ field }) => (
                    <FileDropzone
                      value={field.value ?? null}
                      onChange={field.onChange}
                      label={t("idDrop")}
                      hint={t("idHint")}
                      clearLabel={tv("fileRequired")}
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
              {t("create")}
            </Button>
          )}
        </div>
      </form>
    </div>
  );
}
