import createMiddleware from "next-intl/middleware";
import { NextResponse, type NextRequest } from "next/server";
import { routing } from "@/i18n/routing";
import { ROLE_COOKIE, TOKEN_COOKIE, dashboardFor } from "@/lib/auth";

const intlMiddleware = createMiddleware(routing);

const PROTECTED_PREFIXES = ["/owner", "/freelancer", "/admin"];
const AUTH_PAGE_PREFIXES = ["/login", "/register", "/forgot-password", "/reset-password"];

/** Strip a leading "/en" (or any known locale) to compare against route prefixes. */
function stripLocale(pathname: string): { locale: string; rest: string } {
  for (const locale of routing.locales) {
    if (pathname === `/${locale}`) return { locale, rest: "/" };
    if (pathname.startsWith(`/${locale}/`)) {
      return { locale, rest: pathname.slice(locale.length + 1) };
    }
  }
  return { locale: routing.defaultLocale, rest: pathname };
}

/** Re-attach the locale prefix (default locale has none under as-needed). */
function withLocale(locale: string, path: string): string {
  if (locale === routing.defaultLocale) return path;
  return `/${locale}${path === "/" ? "" : path}`;
}

function matchesPrefix(rest: string, prefixes: string[]): boolean {
  return prefixes.some((p) => rest === p || rest.startsWith(`${p}/`));
}

export default function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const { locale, rest } = stripLocale(pathname);

  const token = request.cookies.get(TOKEN_COOKIE)?.value;
  const role = request.cookies.get(ROLE_COOKIE)?.value;
  const isAuthenticated = !!token;

  // Unauthenticated user hitting a protected dashboard -> /login?redirect=...
  if (!isAuthenticated && matchesPrefix(rest, PROTECTED_PREFIXES)) {
    const url = request.nextUrl.clone();
    url.pathname = withLocale(locale, "/login");
    url.search = `?redirect=${encodeURIComponent(rest)}`;
    return NextResponse.redirect(url);
  }

  // Authenticated user hitting an auth page -> their dashboard.
  if (isAuthenticated && matchesPrefix(rest, AUTH_PAGE_PREFIXES)) {
    const url = request.nextUrl.clone();
    url.pathname = withLocale(locale, dashboardFor(role));
    url.search = "";
    return NextResponse.redirect(url);
  }

  // Authenticated user in the wrong role's dashboard -> own dashboard.
  if (isAuthenticated && role) {
    const ownDashboard = dashboardFor(role);
    const inAnotherDashboard = PROTECTED_PREFIXES.some(
      (p) => (rest === p || rest.startsWith(`${p}/`)) && p !== ownDashboard,
    );
    if (inAnotherDashboard) {
      const url = request.nextUrl.clone();
      url.pathname = withLocale(locale, ownDashboard);
      url.search = "";
      return NextResponse.redirect(url);
    }
  }

  return intlMiddleware(request);
}

export const config = {
  // Run on everything except API routes, Next internals, and static assets.
  matcher: ["/((?!api|_next|_vercel|.*\\..*).*)"],
};
