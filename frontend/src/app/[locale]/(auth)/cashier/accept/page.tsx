"use client";

import { Suspense, useEffect, useState, useTransition, type FormEvent } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import type { ClientAuthResult } from "@/lib/types/auth";

interface InvitationPreview {
  email: string;
  name: string;
  workspace_name: string;
}

/**
 * Public cashier invite-accept screen. Loads the invitation preview for the
 * `?token=` (workspace + invited email), then lets the invitee set a name +
 * password. On success the route handler sets the session cookies; we refresh
 * the auth context and land on the cashier dashboard.
 */
function CashierAccept() {
  const t = useTranslations("cashier.accept");
  const router = useRouter();
  const params = useSearchParams();
  const { refreshUser } = useAuth();

  const token = params.get("token");

  const [preview, setPreview] = useState<InvitationPreview | null>(null);
  // Derive the no-token state at mount so the effect never sets state synchronously.
  const [loadingPreview, setLoadingPreview] = useState(() => Boolean(token));
  const [invalidInvite, setInvalidInvite] = useState(() => !token);

  const [name, setName] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  useEffect(() => {
    if (!token) return;

    let active = true;
    (async () => {
      try {
        const res = await fetch(
          `/api/cashier/invitation?token=${encodeURIComponent(token)}`,
          { credentials: "include" },
        );
        if (!res.ok) throw new Error("invalid_invite");
        const data = (await res.json()) as InvitationPreview;
        if (!active) return;
        setPreview(data);
        setName(data.name ?? "");
      } catch {
        if (active) setInvalidInvite(true);
      } finally {
        if (active) setLoadingPreview(false);
      }
    })();

    return () => {
      active = false;
    };
  }, [token]);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!token) return;

    setErrors({});
    setFormError(null);

    startTransition(async () => {
      try {
        const res = await fetch("/api/cashier/accept", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "include",
          body: JSON.stringify({
            token,
            name,
            password,
            password_confirmation: passwordConfirmation,
          }),
        });

        const body = (await res.json()) as
          | ClientAuthResult
          | { message?: string; errors?: Record<string, string[]> };

        if (!res.ok) {
          const err = body as { message?: string; errors?: Record<string, string[]> };
          setErrors(err.errors ?? {});
          setFormError(err.message ?? t("error"));
          return;
        }

        await refreshUser();
        router.replace("/cashier");
      } catch {
        setFormError(t("error"));
      }
    });
  }

  if (loadingPreview) {
    return (
      <div className="auth-form" style={{ textAlign: "center" }}>
        <div
          className="row"
          style={{ gap: 12, justifyContent: "center", alignItems: "center" }}
        >
          <span className="btn-spinner" aria-hidden="true" />
          <span className="muted">{t("loading")}</span>
        </div>
      </div>
    );
  }

  if (invalidInvite || !preview) {
    return (
      <div className="auth-form">
        <Alert tone="danger" title={t("invalidInvite")} />
      </div>
    );
  }

  return (
    <form className="auth-form" onSubmit={handleSubmit}>
      <h1 className="h1">{t("title")}</h1>
      <p className="muted" style={{ marginTop: 6, marginBottom: 20 }}>
        {t("subtitle", { workspace: preview.workspace_name })}
      </p>

      <div style={{ marginBottom: 20 }}>
        <Alert
          tone="info"
          title={t("welcome", { workspace: preview.workspace_name })}
        />
      </div>

      {formError && (
        <div style={{ marginBottom: 16 }}>
          <Alert tone="danger" title={formError} />
        </div>
      )}

      <div className="stack" style={{ gap: 16 }}>
        <Field label={t("email")} htmlFor="cashier-email">
          <Input
            id="cashier-email"
            type="email"
            value={preview.email}
            readOnly
            className="ltr"
          />
        </Field>

        <Field label={t("name")} htmlFor="cashier-name" error={errors.name?.[0]}>
          <Input
            id="cashier-name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            autoComplete="name"
            required
          />
        </Field>

        <Field
          label={t("password")}
          htmlFor="cashier-password"
          error={errors.password?.[0]}
        >
          <Input
            id="cashier-password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="new-password"
            required
          />
        </Field>

        <Field label={t("confirm")} htmlFor="cashier-password-confirm">
          <Input
            id="cashier-password-confirm"
            type="password"
            value={passwordConfirmation}
            onChange={(e) => setPasswordConfirmation(e.target.value)}
            autoComplete="new-password"
            required
          />
        </Field>

        <Button type="submit" size="lg" block loading={pending} icon="check">
          {t("submit")}
        </Button>
      </div>
    </form>
  );
}

export default function CashierAcceptPage() {
  return (
    <div className="auth-center">
      <Suspense fallback={<div className="auth-form" />}>
        <CashierAccept />
      </Suspense>
    </div>
  );
}
