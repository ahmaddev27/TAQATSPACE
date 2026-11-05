"use client";

import { useCallback, useEffect, useRef, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { useToast } from "@/components/providers/ToastProvider";
import { getRecalculationStatus, startRecalculation } from "@/lib/actions/owner";
import { formatDateTime } from "@/lib/dates";
import type { RecalculationState, RecalculationStatus } from "@/lib/types/tools";

const POLL_MS = 3000;

const STATE_TONE: Record<RecalculationState, "neutral" | "info" | "success" | "danger"> = {
  idle: "neutral",
  queued: "info",
  running: "info",
  completed: "success",
  failed: "danger",
};

/** "Recalculate" tool: rebuilds attendance and statistics for a date range and follows the run until it ends. */
export function RecalculateCard() {
  const t = useTranslations("owner.tools");
  const confirm = useConfirm();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [status, setStatus] = useState<RecalculationStatus | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const wasActive = useRef(false);

  const refresh = useCallback(async () => {
    const res = await getRecalculationStatus();
    if (res.ok) setStatus(res.data);
  }, []);

  useEffect(() => {
    let live = true;
    getRecalculationStatus().then((res) => {
      if (live && res.ok) setStatus(res.data);
    });
    return () => {
      live = false;
    };
  }, []);

  const active = status?.active ?? false;

  // Poll only while a run is in flight, and announce its end once.
  useEffect(() => {
    if (!active) {
      if (wasActive.current && status) {
        toast({
          tone: status.state === "completed" ? "ok" : "err",
          title: status.state === "completed" ? t("finished") : t("failedTitle"),
        });
      }
      wasActive.current = false;
      return;
    }
    wasActive.current = true;
    const timer = setInterval(() => void refresh(), POLL_MS);
    return () => clearInterval(timer);
  }, [active, status, refresh, toast, t]);

  const submit = async () => {
    const proceed = await confirm({
      title: t("confirmTitle"),
      message: t("confirmMessage", { from, to }),
      confirmLabel: t("confirmAction"),
      icon: "alert",
    });
    if (!proceed) return;

    startTransition(async () => {
      const res = await startRecalculation({ from, to });
      if (res.ok) {
        setErrors({});
        setStatus(res.data);
        toast({ tone: "ok", title: t("started") });
        return;
      }
      setErrors(res.errors ?? {});
      toast({ tone: "err", title: t("failedToStart"), body: res.message });
      // A 409 means another run is going: show it instead of leaving a stale card.
      if (res.status === 409) void refresh();
    });
  };

  const ready = from !== "" && to !== "" && !active && !pending;

  return (
    <div className="stack" style={{ gap: 16 }} data-testid="recalculate-card">
      <div className="between wrap" style={{ gap: 12 }}>
        <h4 className="h4">{t("title")}</h4>
        {status && (
          <Badge tone={STATE_TONE[status.state]} dot>
            <span data-testid="recalculate-state" data-state={status.state}>
              {t(`state.${status.state}`)}
            </span>
          </Badge>
        )}
      </div>
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {t("description")}
      </p>

      <div className="grid2">
        <Field label={t("from")} error={errors.from?.[0]}>
          <Input className="ltr" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        </Field>
        <Field label={t("to")} hint={t("rangeHint")} error={errors.to?.[0]}>
          <Input className="ltr" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
        </Field>
      </div>

      <Button
        variant="primary"
        icon="refresh"
        loading={pending || active}
        disabled={!ready}
        onClick={submit}
        style={{ alignSelf: "flex-start" }}
      >
        {t("recalculate")}
      </Button>

      {status && status.state !== "idle" && (
        <p className="muted" style={{ fontSize: "var(--fs-sm)" }} data-testid="recalculate-summary">
          {t("lastRun", { from: status.from ?? "", to: status.to ?? "" })}
          {status.requested_at && ` · ${formatDateTime(status.requested_at)}`}
          {status.state === "completed" &&
            status.written &&
            ` · ${t("written", { days: status.written.days, months: status.written.subscriber_months })}`}
        </p>
      )}
    </div>
  );
}
