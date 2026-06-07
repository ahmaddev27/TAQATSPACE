"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Link, useRouter } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { loginSchema, type LoginValues } from "@/lib/validations/auth";
import type { ClientAuthResult } from "@/lib/types/auth";

/**
 * Dedicated email/password sign-in for internal staff (admins). The public site
 * is SSO-only; this entrance is intentionally un-advertised (no nav link / CTA).
 *
 * It posts through the existing `/api/auth/login` proxy directly (rather than
 * `AuthProvider.login`, which auto-routes by role) so it can enforce an
 * admin-only outcome: a non-admin who authenticates here is signed back out and
 * pointed at the unified login.
 */
export function AdminLoginForm() {
  const t = useTranslations("auth.adminLogin");
  const tv = useTranslations("validation");
  const router = useRouter();
  const { refreshUser } = useAuth();

  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema(tv)),
    mode: "onTouched",
    defaultValues: { email: "", password: "" },
  });

  async function onSubmit(values: LoginValues) {
    setServerError(null);
    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ email: values.email, password: values.password }),
      });

      if (res.status === 401 || res.status === 422) {
        setServerError(t("invalidCredentials"));
        return;
      }
      if (!res.ok) {
        setServerError(t("genericError"));
        return;
      }

      const result = (await res.json()) as ClientAuthResult;

      // Staff entrance only: any non-admin is signed back out (clears the session
      // cookies just set) and told to use the unified login. We hit the logout
      // proxy directly rather than AuthProvider.logout so the page stays put and
      // the message renders, instead of navigating away mid-flow.
      if (result.role !== "admin") {
        await fetch("/api/auth/logout", { method: "POST", credentials: "include" });
        await refreshUser();
        setServerError(t("notStaff"));
        return;
      }

      await refreshUser();
      router.replace("/admin");
    } catch {
      setServerError(t("genericError"));
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
            type="email"
            icon="mail"
            className="ltr"
            autoComplete="username"
            placeholder={t("emailPlaceholder")}
            {...register("email")}
          />
        </Field>

        <Field label={t("password")} error={errors.password?.message}>
          <Input
            type="password"
            icon="lock"
            autoComplete="current-password"
            {...register("password")}
          />
        </Field>

        <Button type="submit" variant="primary" size="lg" block loading={isSubmitting}>
          {t("submit")}
        </Button>
      </div>

      <p style={{ textAlign: "center", marginTop: 24 }}>
        <Link className="link" href="/">
          {t("backHome")}
        </Link>
      </p>
    </form>
  );
}
