"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Alert } from "@/components/ui/Alert";
import { Icon } from "@/components/ui/Icon";

/**
 * Base URL of the Laravel API, e.g. https://api.taqat.space/api. Inlined into
 * the client bundle by Next at build time (NEXT_PUBLIC_*).
 */
const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

/** Full-page SSO start: the backend responds with a 302 to the IdP. */
const SSO_REDIRECT_URL = `${API_URL}/auth/taqat/redirect`;

/**
 * SSO-only launch screen. Login and registration are both delegated to the
 * unified national system (Taqat). The single primary action performs a
 * full-page navigation to the backend SSO start endpoint, which 302s to the
 * IdP. There is no local email/password form.
 */
export function LoginForm({
  loggedOut = false,
  ssoError = false,
}: {
  loggedOut?: boolean;
  ssoError?: boolean;
}) {
  const tSso = useTranslations("auth.sso");

  return (
    <div className="auth-form">
      <h1 className="h1">{tSso("launchTitle")}</h1>
      <p className="muted" style={{ marginTop: 6, marginBottom: 28 }}>
        {tSso("launchSubtitle")}
      </p>

      {loggedOut && (
        <div style={{ marginBottom: 16 }}>
          <Alert tone="success" title={tSso("loggedOut")} />
        </div>
      )}

      {ssoError && (
        <div style={{ marginBottom: 16 }}>
          <Alert tone="danger" title={tSso("error")} />
        </div>
      )}

      <a className="btn btn-primary btn-lg btn-block" href={SSO_REDIRECT_URL}>
        <Icon name="shield" size={18} />
        {tSso("continue")}
      </a>

      <p
        className="muted-3"
        style={{ textAlign: "center", marginTop: 16, fontSize: "var(--fs-sm)" }}
      >
        {tSso("launchNote")}
      </p>

      <p style={{ textAlign: "center", marginTop: 24 }}>
        <Link className="link" href="/">
          {tSso("backHome")}
        </Link>
      </p>
    </div>
  );
}
