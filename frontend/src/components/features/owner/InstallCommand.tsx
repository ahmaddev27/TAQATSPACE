"use client";

import { useEffect, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import { generateInstallCommand } from "@/lib/actions/owner";
import type { RouterInstallToken } from "@/lib/types/router";

export interface InstallCommandProps {
  routerId: string;
}

function secondsLeft(expiresAt: string): number {
  return Math.max(0, Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000));
}

function formatCountdown(total: number): string {
  const minutes = Math.floor(total / 60);
  const seconds = total % 60;
  return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
}

/**
 * Generates the one-time `/tool fetch` install command. It lives in component
 * state only: it is never written to storage, the URL or the console, and
 * generating a new one invalidates the previous one on the server.
 */
export function InstallCommand({ routerId }: InstallCommandProps) {
  const t = useTranslations("integration.install");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [token, setToken] = useState<RouterInstallToken | null>(null);
  const [remaining, setRemaining] = useState(0);

  useEffect(() => {
    if (!token) return;
    const tick = () => setRemaining(secondsLeft(token.expires_at));
    tick();
    const timer = window.setInterval(tick, 1000);
    return () => window.clearInterval(timer);
  }, [token]);

  const generate = () =>
    startTransition(async () => {
      const res = await generateInstallCommand(routerId);
      if (res.ok) {
        setToken(res.data);
      } else {
        toast({ tone: "err", title: t("failed"), body: res.message });
      }
    });

  const copy = () => {
    if (!token) return;
    void navigator.clipboard?.writeText(token.command);
    toast({ tone: "ok", title: t("copied") });
  };

  const expired = token !== null && remaining === 0;

  return (
    <div className="stack" style={{ gap: 12 }}>
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {t("hint")}
      </p>
      <Button
        variant="primary"
        icon="zap"
        loading={pending}
        onClick={generate}
        style={{ alignSelf: "flex-start" }}
      >
        {token ? t("regenerate") : t("generate")}
      </Button>

      {token && (
        <div className="stack" style={{ gap: 8 }}>
          <pre className="ltr" data-testid="install-command" style={{ whiteSpace: "pre-wrap", wordBreak: "break-all" }}>
            {token.command}
          </pre>
          <div className="between wrap" style={{ gap: 8 }}>
            <span className={expired ? "badge badge-danger" : "badge badge-info"} role="timer">
              {expired ? t("expired") : t("expiresIn", { time: formatCountdown(remaining) })}
            </span>
            <Button variant="secondary" size="sm" icon="doc" onClick={copy} disabled={expired}>
              {t("copy")}
            </Button>
          </div>
          <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("onceNote")}
          </p>
        </div>
      )}
    </div>
  );
}
