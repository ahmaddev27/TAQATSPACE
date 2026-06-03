import { redirect } from "next/navigation";
import { setRequestLocale } from "next-intl/server";
import { serverFetch, ApiError } from "@/lib/api";
import { isKnownRole } from "@/lib/auth";
import { DashShell } from "@/components/layout/DashShell";
import type { ApiEnvelope, User } from "@/lib/types";

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

  // Pending/suspended accounts hold a session token but the role-guarded APIs
  // (rightly) reject them. Show a clear notice instead of a 500. Link to home
  // (not /login — middleware would bounce an authenticated user back here).
  if (user.status !== "active") {
    const isAr = locale === "ar";
    const prefix = `/${locale}`;
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
            maxWidth: 420,
            textAlign: "center",
            background: "var(--surface)",
            border: "1px solid var(--border)",
            borderRadius: "var(--r-tile)",
            padding: "var(--s-8)",
            boxShadow: "var(--sh-sm)",
          }}
        >
          <h2 className="h3">
            {isAr ? "حسابك قيد التحقق" : "Account pending verification"}
          </h2>
          <p style={{ color: "var(--text-2)", margin: "var(--s-3) 0 var(--s-6)" }}>
            {isAr
              ? "يرجى تفعيل بريدك الإلكتروني للوصول إلى لوحة التحكم. تحقّق من بريدك الوارد."
              : "Please verify your email to access your dashboard. Check your inbox."}
          </p>
          <a className="btn btn-primary" href={`${prefix}/`}>
            {isAr ? "العودة للرئيسية" : "Back to home"}
          </a>
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
