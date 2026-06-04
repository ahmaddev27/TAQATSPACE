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
    try {
      await fetch("/api/auth/logout", { method: "POST", credentials: "include" });
    } catch {
      // Cookies are cleared server-side regardless; never block sign-out on a
      // network hiccup.
    }
    setUser(null);
    // Land on login with a confirmation flag, then refresh so cached authed RSC
    // payloads (dashboard shell) are discarded.
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
