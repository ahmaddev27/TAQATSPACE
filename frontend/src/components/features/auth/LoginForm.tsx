"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations, useLocale } from "next-intl";
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

/** Seeded demo logins (all share the password below). Remove before public launch. */
const DEMO_PASSWORD = "password";
const DEMO_ACCOUNTS: Array<{ key: string; ar: string; en: string; email: string }> = [
  { key: "owner", ar: "مالك مساحة", en: "Workspace owner", email: "owner@taqat.space" },
  { key: "freelancer", ar: "مستقل", en: "Freelancer", email: "freelancer@taqat.space" },
  { key: "admin", ar: "مدير المنصّة", en: "Admin", email: "admin@taqat.space" },
];

export function LoginForm() {
  const t = useTranslations("auth.login");
  const tv = useTranslations("validation");
  const locale = useLocale();
  const { login } = useAuth();
  const { toast } = useToast();
  const [showPassword, setShowPassword] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema(tv)),
    defaultValues: { email: "", password: "", remember: true },
  });

  function fillDemo(email: string) {
    setValue("email", email, { shouldValidate: true });
    setValue("password", DEMO_PASSWORD, { shouldValidate: true });
    setServerError(null);
  }

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

      <div className="demo-accounts">
        <div className="demo-accounts-h">
          {locale === "ar" ? "حسابات تجريبية — اضغط للتعبئة" : "Demo accounts — click to fill"}
        </div>
        <div className="demo-accounts-list">
          {DEMO_ACCOUNTS.map((a) => (
            <button
              key={a.key}
              type="button"
              className="demo-chip"
              onClick={() => fillDemo(a.email)}
            >
              <span className="demo-chip-role">{locale === "ar" ? a.ar : a.en}</span>
              <span className="demo-chip-email">{a.email}</span>
            </button>
          ))}
        </div>
        <div className="demo-accounts-pw">
          {locale === "ar" ? "كلمة المرور" : "Password"}: <code>{DEMO_PASSWORD}</code>
        </div>
      </div>
    </form>
  );
}
