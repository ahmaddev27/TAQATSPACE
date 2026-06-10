"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Textarea } from "@/components/ui/Textarea";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import {
  freelancerOnboardingSchema,
  GENDER_OPTIONS,
  SPECIALTY_OPTIONS,
  type FreelancerOnboardingValues,
} from "@/lib/validations/auth";
import type { OnboardingResult } from "./OnboardingFlow";

/**
 * Freelancer branch of SSO onboarding: phone + specialty (+ optional bio). On
 * success the account is active and the parent routes to the dashboard.
 */
export function FreelancerOnboardingForm({
  defaultPhone = "",
  onBack,
  onDone,
}: {
  defaultPhone?: string;
  onBack: () => void;
  onDone: (result: OnboardingResult) => void;
}) {
  const t = useTranslations("auth.onboarding");
  const tf = useTranslations("auth.registerFreelancer");
  const tv = useTranslations("validation");
  const tCommon = useTranslations("common");
  const tg = useTranslations("common.gender");

  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FreelancerOnboardingValues>({
    resolver: zodResolver(freelancerOnboardingSchema(tv)),
    mode: "onTouched",
    defaultValues: { phone: defaultPhone, gender: "", specialty: "", bio: "" },
  });

  async function onSubmit(values: FreelancerOnboardingValues) {
    setServerError(null);
    try {
      const res = await fetch("/api/auth/onboarding", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({
          role: "freelancer",
          ...values,
          // Empty select = "prefer not to say" → null (unspecified) for the API.
          gender: values.gender === "" ? null : values.gender,
        }),
      });

      const body = (await res.json().catch(() => ({}))) as Partial<OnboardingResult> & {
        message?: string;
        errors?: Record<string, string[]>;
      };

      if (res.ok && body.role) {
        onDone(body as OnboardingResult);
        return;
      }

      if (res.status === 422 && body.errors) {
        for (const [field, messages] of Object.entries(body.errors)) {
          setError(field as keyof FreelancerOnboardingValues, { message: messages[0] });
        }
      } else {
        setServerError(body.message ?? t("error"));
      }
    } catch {
      setServerError(t("error"));
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <div className="card reg-card">
        {serverError && (
          <div style={{ marginBottom: 16 }}>
            <Alert tone="danger" title={serverError} />
          </div>
        )}

        <div className="stack" style={{ gap: 16 }}>
          <Field label={tf("phone")} hint={tf("phoneHint")} error={errors.phone?.message}>
            <Input
              icon="phone"
              className="ltr"
              placeholder={tf("phonePlaceholder")}
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

          <Field label={tf("specialty")} error={errors.specialty?.message}>
            <Select defaultValue="" {...register("specialty")}>
              <option value="" disabled>
                —
              </option>
              {SPECIALTY_OPTIONS.map((code) => (
                <option key={code} value={code}>
                  {tf(`specialtyOptions.${code}`)}
                </option>
              ))}
            </Select>
          </Field>

          <Field label={tf("bio")} hint={tf("bioHint")} error={errors.bio?.message}>
            <Textarea placeholder={tf("bioPlaceholder")} {...register("bio")} />
          </Field>
        </div>
      </div>

      <div className="between" style={{ marginTop: 24 }}>
        <Button type="button" variant="ghost" icon="arrowL" onClick={onBack}>
          {tCommon("back")}
        </Button>
        <Button type="submit" variant="primary" iconEnd="check" loading={isSubmitting}>
          {t("finish")}
        </Button>
      </div>
    </form>
  );
}
