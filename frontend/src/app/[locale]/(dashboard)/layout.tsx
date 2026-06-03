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
      const prefix = locale === "en" ? "/en" : "";
      redirect(`${prefix}/login`);
    }
    throw error;
  }

  if (!isKnownRole(user.role)) {
    const prefix = locale === "en" ? "/en" : "";
    redirect(`${prefix}/login`);
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
