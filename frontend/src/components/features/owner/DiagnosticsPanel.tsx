"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import { diagnoseRouter } from "@/lib/actions/owner";
import type { DiagnosticCheck, DiagnosticStatus } from "@/lib/types/router";

const TONES: Record<DiagnosticStatus, "success" | "warning" | "danger"> = {
  pass: "success",
  warning: "warning",
  fail: "danger",
};

export interface DiagnosticsPanelProps {
  routerId: string;
}

/** Deep integration check: one pass / warning / fail row per check, with the reason. */
export function DiagnosticsPanel({ routerId }: DiagnosticsPanelProps) {
  const t = useTranslations("integration.diagnostics");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [checks, setChecks] = useState<DiagnosticCheck[] | null>(null);

  const run = () =>
    startTransition(async () => {
      const res = await diagnoseRouter(routerId);
      if (res.ok) {
        setChecks(res.data.checks);
      } else {
        toast({ tone: "err", title: t("failed"), body: res.message });
      }
    });

  return (
    <div className="stack" style={{ gap: 12 }}>
      <div className="row" style={{ justifyContent: "space-between", gap: 12 }}>
        <p className="muted" style={{ margin: 0 }}>
          {t("hint")}
        </p>
        <Button icon="refresh" onClick={run} disabled={pending}>
          {pending ? t("running") : t("run")}
        </Button>
      </div>

      {checks !== null && (
        <ul className="stack" style={{ gap: 10, listStyle: "none", padding: 0, margin: 0 }} data-testid="diagnostic-checks">
          {checks.map((check) => (
            <li key={check.name} className="row" style={{ gap: 12, alignItems: "flex-start" }}>
              <Badge tone={TONES[check.status]} dot>
                {t(`status.${check.status}`)}
              </Badge>
              <div>
                <div style={{ fontWeight: 600 }}>{t(`checks.${check.name}`)}</div>
                <div className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                  {check.detail}
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
