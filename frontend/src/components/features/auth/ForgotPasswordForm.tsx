"use client";

import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import {
  forgotPasswordSchema,
  type ForgotPasswordValues,
} from "@/lib/validations/auth";

const COOLDOWN_SECONDS = 60;

export function ForgotPasswordForm() {
  const t = useTranslations("auth.forgot");
  const tv = useTranslations("validation");

  const [sent, setSent] = useState(false);
  const [cooldown, setCooldown] = useState(0);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordValues>({
    resolver: zodResolver(forgotPasswordSchema(tv)),
    defaultValues: { email: "" },
  });

  // Drive the resend cooldown purely from state.
  useEffect(() => {
    if (cooldown <= 0) return;
    const id = setTimeout(() => setCooldown((c) => c - 1), 1000);
    return () => clearTimeout(id);
  }, [cooldown]);

  async function onSubmit(values: ForgotPasswordValues) {
    // Always show the neutral success message (no account enumeration).
    try {
      await fetch("/api/auth/forgot-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: values.email }),
      });
    } catch {
      // ignored — neutral response either way
    }
    setSent(true);
    setCooldown(COOLDOWN_SECONDS);
  }

  return (
    <form className="auth-form" onSubmit={handleSubmit(onSubmit)} noValidate>
      <h1 className="h1">{t("title")}</h1>
      <p className="muted" style={{ marginTop: 6, marginBottom: 28 }}>
        {t("subtitle")}
      </p>

      {sent && (
        <div style={{ marginBottom: 16 }}>
          <Alert tone="success" title={t("success")} />
        </div>
      )}

      <div className="stack" style={{ gap: 16 }}>
        <Field label={t("email")} error={errors.email?.message}>
          <Input
            icon="mail"
            type="email"
            autoComplete="email"
            placeholder="name@mail.ps"
            {...register("email")}
          />
        </Field>

        <Button
          type="submit"
          variant="primary"
          size="lg"
          block
          loading={isSubmitting}
          disabled={cooldown > 0}
        >
          {cooldown > 0 ? t("resendIn", { seconds: cooldown }) : t("submit")}
        </Button>
      </div>

      <p
        className="muted"
        style={{ textAlign: "center", marginTop: 24, fontSize: "var(--fs-sm)" }}
      >
        <Link className="link" href="/login">
          {t("backToLogin")}
        </Link>
      </p>
    </form>
  );
}
