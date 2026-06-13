"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { useRouter } from "@/i18n/navigation";
import { dashboardFor } from "@/lib/auth";
import { unregisterPush } from "@/lib/firebase/messaging";
import { signOutFromChat } from "@/lib/firebase/chat";
import type { ClientAuthResult, User, UserRole } from "@/lib/types/auth";

interface AuthContextValue {
  user: User | null;
  role: UserRole | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string, remember?: boolean) => Promise<ClientAuthResult>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const refreshUser = useCallback(async () => {
    try {
      const res = await fetch("/api/auth/me", { credentials: "include" });
      if (res.ok) {
        const data = (await res.json()) as { user: User };
        setUser(data.user);
      } else {
        setUser(null);
      }
    } catch {
      setUser(null);
    }
  }, []);

  useEffect(() => {
    let active = true;
    (async () => {
      await refreshUser();
      if (active) setIsLoading(false);
    })();
    return () => {
      active = false;
    };
  }, [refreshUser]);

  const login = useCallback<AuthContextValue["login"]>(
    async (email, password, remember) => {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ email, password, remember }),
      });

      const payload = (await res.json().catch(() => ({}))) as
        | ClientAuthResult
        | { message?: string };

      if (!res.ok) {
        const message = "message" in payload ? payload.message : undefined;
        const err = new Error(message ?? "Login failed") as Error & {
          status?: number;
        };
        err.status = res.status;
        throw err;
      }

      const result = payload as ClientAuthResult;
      setUser(result.user);
      router.push(dashboardFor(result.role));
      return result;
    },
    [router],
  );

  const logout = useCallback<AuthContextValue["logout"]>(async () => {
    // 0) Unregister FCM push while the session is still authenticated — the
    //    device-token DELETE needs the bearer cookie that step 1 clears, so
    //    doing it afterwards would 401. unregisterPush is idempotent + silent.
    await unregisterPush();

    // Drop the Firebase Auth identity + cached chat session, so the NEXT account
    // to sign in this browser gets its own uid (else Firestore denies its chat
    // reads and the conversation list shows empty). Best-effort.
    await signOutFromChat();

    // 1) Clear the local session (delete the Sanctum token + httpOnly cookies).
    //    Also capture the backend-built SSO logout URL as a fallback.
    let backendSsoLogoutUrl: string | null = null;
    try {
      const res = await fetch("/api/auth/logout", {
        method: "POST",
        credentials: "include",
      });
      const body = (await res.json().catch(() => null)) as
        | { sso_logout_url?: string | null }
        | null;
      backendSsoLogoutUrl = body?.sso_logout_url ?? null;
    } catch {
      // Cookies are cleared server-side regardless; never block sign-out.
    }
    setUser(null);

    // 2) End the IdP session too (single logout). Prefer the BACKEND-built URL:
    //    it carries the `id_token_hint` and the exact registered
    //    `post_logout_redirect_uri` the IdP requires — neither of which the
    //    client can reproduce (the id_token never leaves the server). Sending an
    //    end-session request without those is rejected by the provider.
    if (backendSsoLogoutUrl) {
      window.location.href = backendSsoLogoutUrl;
      return;
    }
    // Fallback only: a client-built end-session URL from NEXT_PUBLIC envs, used
    // when the backend returned nothing (e.g. the id_token was not retained).
    // The redirect must match a URI registered at the IdP, so include the
    // `/login?loggedout=1` path (not just the bare origin).
    const ssoEndpoint = process.env.NEXT_PUBLIC_SSO_LOGOUT_URL;
    const ssoClientId = process.env.NEXT_PUBLIC_SSO_CLIENT_ID;
    if (ssoEndpoint && ssoClientId) {
      const params = new URLSearchParams({
        post_logout_redirect_uri: `${window.location.origin}/login?loggedout=1`,
        client_id: ssoClientId,
      });
      window.location.assign(`${ssoEndpoint}?${params.toString()}`);
      return;
    }

    // 3) Local-only fallback: land on login, then refresh so cached authed RSC
    //    payloads (dashboard shell) are discarded.
    router.replace("/login?loggedout=1");
    router.refresh();
  }, [router]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      role: user?.role ?? null,
      isLoading,
      isAuthenticated: !!user,
      login,
      logout,
      refreshUser,
    }),
    [user, isLoading, login, logout, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within an AuthProvider");
  return ctx;
}
