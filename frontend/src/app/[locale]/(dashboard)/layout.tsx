import { redirect } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { serverFetch, ApiError } from "@/lib/api";
import { isKnownRole } from "@/lib/auth";
import { DashShell } from "@/components/layout/DashShell";
import { AccountStateActions } from "@/components/features/auth/AccountStateActions";
import type { ApiEnvelope, User } from "@/lib/types";

/**
 * Pick the gate title/body for a non-active account. Email verification is no
 * longer a possible reason (SSO verifies email at provisioning), so the copy is
 * about approval/suspension instead.
 */
function accountStateCopy(
  user: User,
  t: (key: string) => string,
): { title: string; body: string } {
  if (user.role === "workspace_owner" && user.status !== "suspended") {
    return { title: t("ownerPendingTitle"), body: t("ownerPendingBody") };
  }
  if (user.status === "suspended") {
    return { title: t("suspendedTitle"), body: t("suspendedBody") };
  }
  return { title: t("genericTitle"), body: t("genericBody") };
}

/** First grapheme of a name (works for Arabic + Latin), uppercased. */
function avatarInitial(name: string): string {
  const first = Array.from(name.trim())[0] ?? "?";
  return first.toUpperCase();
}

export default async function DashboardLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  let user: User;
  try {
    const res = await serverFetch<ApiEnvelope<{ user: User }>>("/auth/me");
    user = res.data.user;
  } catch (error) {
    // Middleware normally guards these routes; this is defense-in-depth for an
    // expired/invalid token reaching the Server Component.
    const status = error instanceof ApiError ? error.status : 401;
    if (status === 401 || status === 403) {
      const prefix = `/${locale}`;
      redirect(`${prefix}/login`);
    }
    throw error;
  }

  if (!isKnownRole(user.role)) {
    const prefix = `/${locale}`;
    redirect(`${prefix}/login`);
  }

  // A freshly-provisioned SSO account that navigated straight to a dashboard URL
  // hasn't picked a role yet — send it to onboarding rather than the gate below.
  if (user.needs_onboarding) {
    redirect(`/${locale}/onboarding`);
  }

  // Non-active accounts hold a session token but the role-guarded APIs (rightly)
  // reject them. Show a contextual notice instead of a 500. SSO already verified
  // the email, so the reason is approval/suspension — never "verify your email".
  if (user.status !== "active") {
    const t = await getTranslations({ locale, namespace: "auth.accountState" });
    const { title, body } = accountStateCopy(user, t);
    return (
      <div
        style={{
          minHeight: "100dvh",
          display: "grid",
          placeItems: "center",
          padding: "var(--s-6)",
          background: "var(--bg)",
        }}
      >
        <div
          style={{
            maxWidth: 440,
            textAlign: "center",
            background: "var(--surface)",
            border: "1px solid var(--border)",
            borderRadius: "var(--r-tile)",
            padding: "var(--s-8)",
            boxShadow: "var(--sh-sm)",
          }}
        >
          <h2 className="h3">{title}</h2>
          <p style={{ color: "var(--text-2)", margin: "var(--s-3) 0 var(--s-5)" }}>
            {body}
          </p>
          <div
            style={{
              marginBottom: "var(--s-6)",
              fontSize: "var(--fs-sm)",
              color: "var(--text-3)",
            }}
          >
            {t("emailLabel")}: <span className="ltr">{user.email}</span>
          </div>
          <AccountStateActions />
        </div>
      </div>
    );
  }

  return (
    <DashShell
      role={user.role}
      userName={user.name}
      avatarInitial={avatarInitial(user.name)}
    >
      {children}
    </DashShell>
  );
}
