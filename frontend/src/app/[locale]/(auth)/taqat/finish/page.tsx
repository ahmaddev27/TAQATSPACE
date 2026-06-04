"use client";

import { Suspense, useEffect, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { dashboardFor } from "@/lib/auth";
import { Alert } from "@/components/ui/Alert";
import type { ClientAuthResult } from "@/lib/types/auth";

/**
 * Landing target after the IdP round-trip. Redeems the one-time `code` for a
 * session (cookies set server-side), then routes to the role dashboard. On any
 * failure it falls back to the login page with the SSO error flag.
 */
function TaqatFinish() {
  const t = useTranslations("auth.sso");
  const router = useRouter();
  const params = useSearchParams();
  const { refreshUser } = useAuth();
  const [failed, setFailed] = useState(false);
  const started = useRef(false);

  useEffect(() => {
    // Guard against React StrictMode double-invocation: the code is one-time.
    if (started.current) return;
    started.current = true;

    const code = params.get("code");
    if (!code) {
      router.replace("/login?sso_error=1");
      return;
    }

    (async () => {
      try {
        const res = await fetch("/api/auth/taqat/finish", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "include",
          body: JSON.stringify({ code }),
        });

        if (!res.ok) {
          throw new Error("exchange_failed");
        }

        const result = (await res.json()) as ClientAuthResult;
        await refreshUser();
        router.replace(dashboardFor(result.role));
      } catch {
        setFailed(true);
        router.replace("/login?sso_error=1");
      }
    })();
  }, [params, router, refreshUser]);

  return (
    <div className="auth-form" style={{ textAlign: "center" }}>
      {failed ? (
        <Alert tone="danger" title={t("error")} />
      ) : (
        <div
          className="row"
          style={{ gap: 12, justifyContent: "center", alignItems: "center" }}
        >
          <span className="btn-spinner" aria-hidden="true" />
          <span className="muted">{t("signingIn")}</span>
        </div>
      )}
    </div>
  );
}

export default function TaqatFinishPage() {
  return (
    <div className="auth-center">
      <Suspense fallback={<div className="auth-form" />}>
        <TaqatFinish />
      </Suspense>
    </div>
  );
}
