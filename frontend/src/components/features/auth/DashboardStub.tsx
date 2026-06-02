"use client";

import { useAuth } from "@/components/providers/AuthProvider";
import { Button } from "@/components/ui/Button";
import { Avatar } from "@/components/ui/Avatar";
import { LanguageToggle } from "@/components/layout/LanguageToggle";
import { ThemeToggle } from "@/components/layout/ThemeToggle";

export interface DashboardStubProps {
  title: string;
}

/** Minimal auth-gated dashboard placeholder (Phase 1). */
export function DashboardStub({ title }: DashboardStubProps) {
  const { user, isLoading, logout } = useAuth();

  return (
    <main className="pub">
      <header className="pub-header">
        <div className="container pub-header-in">
          <span className="logo-word">TAQAT</span>
          <div className="row" style={{ gap: 8 }}>
            <LanguageToggle />
            <ThemeToggle />
          </div>
        </div>
      </header>

      <div
        className="container"
        style={{ paddingBlock: 48, display: "flex", flexDirection: "column", gap: 20 }}
      >
        <h1 className="h1">{title}</h1>

        {isLoading ? (
          <p className="muted">…</p>
        ) : user ? (
          <div className="card card-pad" style={{ maxWidth: 420 }}>
            <div className="row" style={{ gap: 12 }}>
              <Avatar initial={user.name.charAt(0)} size="lg" round />
              <div>
                <div style={{ fontWeight: 600 }}>{user.name}</div>
                <div className="muted-3 ltr" style={{ fontSize: "var(--fs-sm)" }}>
                  {user.email}
                </div>
              </div>
            </div>
            <div className="divider" style={{ margin: "16px 0" }} />
            <Button variant="secondary" icon="logout" onClick={() => logout()}>
              {user.role}
            </Button>
          </div>
        ) : (
          <p className="muted">—</p>
        )}
      </div>
    </main>
  );
}
