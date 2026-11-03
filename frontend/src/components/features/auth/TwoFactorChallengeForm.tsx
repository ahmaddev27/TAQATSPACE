"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { Link, useRouter } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { dashboardFor } from "@/lib/auth";
import type { ClientAuthResult } from "@/lib/types/auth";

/**
 * Second step of sign-in for accounts with 2FA. Accepts an authenticator code
 * or, via the toggle, a single-use recovery code. A passed challenge promotes
 * the parked challenge-only token to the real session.
 */
export function TwoFactorChallengeForm() {
  const t = useTranslations("auth.twoFactor");
  const router = useRouter();
  const { refreshUser } = useAuth();

  const [useRecovery, setUseRecovery] = useState(false);
  const [value, setValue] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const res = await fetch("/api/auth/two-factor/challenge", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(
          useRecovery ? { recovery_code: value.trim() } : { code: value.trim() },
        ),
      });

      if (res.status === 429) return setError(t("throttled"));
      if (res.status === 401) return router.replace("/login");
      if (!res.ok) return setError(t("invalidCode"));

      const result = (await res.json()) as ClientAuthResult;
      await refreshUser();
      router.replace(
        result.user.needs_onboarding ? "/onboarding" : dashboardFor(result.role),
      );
    } catch {
      setError(t("genericError"));
    } finally {
      setBusy(false);
    }
  }

  function toggleMode() {
    setUseRecovery((current) => !current);
    setValue("");
    setError(null);
  }

  return (
    <form className="auth-form" onSubmit={onSubmit} noValidate>
      <h1 className="h1">{t("challengeTitle")}</h1>
      <p className="muted" style={{ marginTop: 6, marginBottom: 28 }}>
        {useRecovery ? t("recoveryHint") : t("challengeHint")}
      </p>

      {error && (
        <div style={{ marginBottom: 16 }}>
          <Alert tone="danger" title={error} />
        </div>
      )}

      <div className="stack" style={{ gap: 16 }}>
        <Field label={useRecovery ? t("recoveryLabel") : t("codeLabel")}>
          <Input
            icon="lock"
            className="ltr"
            inputMode={useRecovery ? "text" : "numeric"}
            autoComplete="one-time-code"
            autoFocus
            maxLength={useRecovery ? 32 : 6}
            value={value}
            onChange={(e) => setValue(e.target.value)}
          />
        </Field>

        <Button
          type="submit"
          variant="primary"
          size="lg"
          block
          loading={busy}
          disabled={value.trim() === ""}
        >
          {t("verify")}
        </Button>
      </div>

      <p style={{ textAlign: "center", marginTop: 24 }}>
        <button type="button" className="link" onClick={toggleMode}>
          {useRecovery ? t("useAuthenticator") : t("useRecovery")}
        </button>
      </p>
      <p style={{ textAlign: "center", marginTop: 8 }}>
        <Link className="link" href="/login">
          {t("backToLogin")}
        </Link>
      </p>
    </form>
  );
}
