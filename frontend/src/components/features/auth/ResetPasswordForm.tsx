"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Link, useRouter } from "@/i18n/navigation";
import { useToast } from "@/components/providers/ToastProvider";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import {
  resetPasswordSchema,
  type ResetPasswordValues,
} from "@/lib/validations/auth";

export interface ResetPasswordFormProps {
  token: string;
  email: string;
}

export function ResetPasswordForm({ token, email }: ResetPasswordFormProps) {
  const t = useTranslations("auth.reset");
  const tv = useTranslations("validation");
  const router = useRouter();
  const { toast } = useToast();

  const [invalid, setInvalid] = useState(false);
  const missingParams = !token || !email;

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordValues>({
    resolver: zodResolver(resetPasswordSchema(tv)),
    defaultValues: { password: "", password_confirmation: "" },
  });

  async function onSubmit(values: ResetPasswordValues) {
    setInvalid(false);
    try {
      const res = await fetch("/api/auth/reset-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          token,
          email,
          password: values.password,
          password_confirmation: values.password_confirmation,
        }),
      });

      if (res.ok) {
        toast({ tone: "ok", title: t("success") });
        router.push("/login");
        return;
      }
      setInvalid(true);
    } catch {
      setInvalid(true);
    }
  }

  if (missingParams || invalid) {
    return (
      <div className="auth-form">
        <h1 className="h1">{t("title")}</h1>
        <div style={{ marginTop: 20 }}>
          <Alert tone="danger" title={t("invalidToken")} />
        </div>
        <p style={{ textAlign: "center", marginTop: 24 }}>
          <Link className="link" href="/forgot-password">
            {t("requestNew")}
          </Link>
        </p>
      </div>
    );
  }

  return (
    <form className="auth-form" onSubmit={handleSubmit(onSubmit)} noValidate>
      <h1 className="h1">{t("title")}</h1>
      <p className="muted" style={{ marginTop: 6, marginBottom: 28 }}>
        {t("subtitle")}
      </p>

      <div className="stack" style={{ gap: 16 }}>
        <Field label={t("password")} error={errors.password?.message}>
          <Input
            icon="lock"
            type="password"
            autoComplete="new-password"
            {...register("password")}
          />
        </Field>
        <Field
          label={t("passwordConfirm")}
          error={errors.password_confirmation?.message}
        >
          <Input
            icon="lock"
            type="password"
            autoComplete="new-password"
            {...register("password_confirmation")}
          />
        </Field>

        <Button
          type="submit"
          variant="primary"
          size="lg"
          block
          loading={isSubmitting}
        >
          {t("submit")}
        </Button>
      </div>
    </form>
  );
}
