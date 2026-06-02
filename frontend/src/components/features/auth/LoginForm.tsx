"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { useToast } from "@/components/providers/ToastProvider";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Alert } from "@/components/ui/Alert";
import { Icon } from "@/components/ui/Icon";
import { loginSchema, type LoginValues } from "@/lib/validations/auth";

export function LoginForm() {
  const t = useTranslations("auth.login");
  const tv = useTranslations("validation");
  const { login } = useAuth();
  const { toast } = useToast();
  const [showPassword, setShowPassword] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema(tv)),
    defaultValues: { email: "", password: "", remember: true },
  });

  async function onSubmit(values: LoginValues) {
    setServerError(null);
    try {
      await login(values.email, values.password, values.remember);
      toast({ tone: "ok", title: t("success") });
    } catch (err) {
      const status = (err as { status?: number }).status;
      if (status === 401) {
        setServerError(t("invalidCredentials"));
      } else {
        setServerError((err as Error).message || t("invalidCredentials"));
      }
    }
  }

  return (
    <form className="auth-form" onSubmit={handleSubmit(onSubmit)} noValidate>
      <h1 className="h1">{t("title")}</h1>
      <p className="muted" style={{ marginTop: 6, marginBottom: 28 }}>
        {t("subtitle")}
      </p>

      {serverError && (
        <div style={{ marginBottom: 16 }}>
          <Alert tone="danger" title={serverError} />
        </div>
      )}

      <div className="stack" style={{ gap: 16 }}>
        <Field label={t("email")} error={errors.email?.message}>
          <Input
            icon="mail"
            type="email"
            autoComplete="email"
            placeholder={t("emailPlaceholder")}
            {...register("email")}
          />
        </Field>

        <Field label={t("password")} error={errors.password?.message}>
          <div className="input-icon">
            <Icon name="lock" />
            <input
              className="input"
              type={showPassword ? "text" : "password"}
              autoComplete="current-password"
              {...register("password")}
            />
            <button
              type="button"
              className="input-eye"
              onClick={() => setShowPassword((v) => !v)}
              aria-label={showPassword ? t("hidePassword") : t("showPassword")}
            >
              <Icon name={showPassword ? "eyeOff" : "eye"} size={18} />
            </button>
          </div>
        </Field>

        <div className="between">
          <Checkbox {...register("remember")}>{t("remember")}</Checkbox>
          <Link className="link" href="/forgot-password">
            {t("forgot")}
          </Link>
        </div>

        <Button type="submit" variant="primary" size="lg" block loading={isSubmitting}>
          {t("submit")}
        </Button>
      </div>

      <p
        className="muted"
        style={{ textAlign: "center", marginTop: 24, fontSize: "var(--fs-sm)" }}
      >
        {t("noAccount")}{" "}
        <Link className="link" href="/register/freelancer">
          {t("signup")}
        </Link>
        {" · "}
        <Link className="link" href="/register/workspace">
          {t("registerWorkspace")}
        </Link>
      </p>
    </form>
  );
}
