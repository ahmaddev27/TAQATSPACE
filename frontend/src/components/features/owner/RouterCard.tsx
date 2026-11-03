"use client";

import { useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { StatTile } from "@/components/ui/StatTile";
import { useToast } from "@/components/providers/ToastProvider";
import { rotateRouterKey, unlockRouter, updateRouterScript } from "@/lib/actions/owner";
import type { OwnerRouter, RouterStatus } from "@/lib/types/router";

const TONES: Record<RouterStatus, "neutral" | "success" | "warning" | "danger" | "info"> = {
  online: "success",
  offline: "danger",
  pending: "warning",
  locked: "danger",
  disabled: "neutral",
};

type SecurityAction = "rotate" | "unlock";

export interface RouterCardProps {
  router: OwnerRouter;
}

/** Status, identity and actions of the workspace router. */
export function RouterCard({ router }: RouterCardProps) {
  const t = useTranslations("integration");
  const locale = useLocale();
  const confirm = useConfirm();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [action, setAction] = useState<SecurityAction | null>(null);
  const [code, setCode] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  // Held in memory only and dropped on close: the key is shown exactly once.
  const [newKey, setNewKey] = useState<string | null>(null);

  const lastSync = router.last_seen_at
    ? new Date(router.last_seen_at).toLocaleString(locale, { dateStyle: "short", timeStyle: "short" })
    : t("card.never");

  const copy = (value: string) => {
    void navigator.clipboard?.writeText(value);
    toast({ tone: "ok", title: t("copied") });
  };

  const close = () => {
    setAction(null);
    setCode("");
    setErrors({});
    setNewKey(null);
  };

  const submit = () =>
    startTransition(async () => {
      const res =
        action === "rotate"
          ? await rotateRouterKey(router.id, code)
          : await unlockRouter(router.id, code);

      if (!res.ok) {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("actionFailed"), body: res.message });
        return;
      }

      if (action === "rotate") {
        setNewKey((res.data as { key: string }).key);
        setCode("");
        return;
      }
      toast({ tone: "ok", title: t("unlock.done") });
      close();
    });

  const pushUpdate = async () => {
    const proceed = await confirm({
      title: t("update.confirmTitle"),
      message: t("update.confirmMessage", { version: router.script_update?.latest_version ?? "" }),
      icon: "refresh",
    });
    if (!proceed) return;

    const res = await updateRouterScript(router.id);
    toast(
      res.ok
        ? { tone: "ok", title: t("update.queued") }
        : { tone: "err", title: t("actionFailed"), body: res.message },
    );
  };

  const updateAvailable = router.script_update?.available === true;

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="between wrap" style={{ gap: 12 }}>
        <div className="row" style={{ gap: 10 }}>
          <h3 className="h3">{router.name}</h3>
          <Badge tone={TONES[router.status]} dot>
            {t(`status.${router.status}`)}
          </Badge>
        </div>
        <div className="row wrap" style={{ gap: 8 }}>
          {updateAvailable && (
            <Button variant="secondary" size="sm" icon="refresh" onClick={pushUpdate}>
              {t("update.action")}
            </Button>
          )}
          {router.status === "locked" && (
            <Button variant="secondary" size="sm" icon="lock" onClick={() => setAction("unlock")}>
              {t("unlock.action")}
            </Button>
          )}
          <Button variant="danger" size="sm" icon="shield" onClick={() => setAction("rotate")}>
            {t("rotate.action")}
          </Button>
        </div>
      </div>

      <div className="grid-stats">
        <StatTile icon="wifi" label={t("card.status")} value={t(`status.${router.status}`)} foot={lastSync} />
        <StatTile icon="clock" label={t("card.lastSync")} value={lastSync} />
        <StatTile
          icon="settings"
          label={t("card.versions")}
          value={<span className="ltr">{router.routeros_version ?? "—"}</span>}
          foot={t("card.scriptVersion", { version: router.script_version ?? "—" })}
        />
        <StatTile
          icon="shield"
          label={t("card.serial")}
          value={<span className="ltr">{router.serial_number ?? "—"}</span>}
        />
      </div>

      <div className="stack" style={{ gap: 10 }}>
        <ValueRow label={t("card.workspaceCode")} value={router.workspace_code} onCopy={copy} copyLabel={t("copy")} />
        <ValueRow label={t("card.endpoint")} value={router.endpoint} onCopy={copy} copyLabel={t("copy")} />
      </div>

      {action && (
        <Modal
          title={newKey ? t("rotate.newKeyTitle") : t(`${action}.modalTitle`)}
          icon="shield"
          onClose={pending ? () => undefined : close}
          footer={
            newKey ? (
              <Button variant="primary" onClick={close}>
                {t("rotate.done")}
              </Button>
            ) : (
              <>
                <Button variant="ghost" onClick={close} disabled={pending}>
                  {t("cancel")}
                </Button>
                <Button variant="danger" loading={pending} onClick={submit}>
                  {t(`${action}.confirm`)}
                </Button>
              </>
            )
          }
        >
          {newKey ? (
            <div className="stack" style={{ gap: 10 }}>
              <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
                {t("rotate.shownOnce")}
              </p>
              <pre className="ltr" data-testid="new-key" style={{ whiteSpace: "pre-wrap", wordBreak: "break-all" }}>
                {newKey}
              </pre>
              <Button variant="secondary" size="sm" icon="doc" onClick={() => copy(newKey)} style={{ alignSelf: "flex-start" }}>
                {t("copy")}
              </Button>
            </div>
          ) : (
            <div className="stack" style={{ gap: 14 }}>
              <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
                {t(`${action}.description`)}
              </p>
              <Field label={t("codeLabel")} hint={t("codeHint")} error={errors.code?.[0]}>
                <Input
                  className="tnum ltr"
                  inputMode="numeric"
                  maxLength={6}
                  autoComplete="one-time-code"
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))}
                />
              </Field>
            </div>
          )}
        </Modal>
      )}
    </div>
  );
}

interface ValueRowProps {
  label: string;
  value: string;
  copyLabel: string;
  onCopy: (value: string) => void;
}

function ValueRow({ label, value, copyLabel, onCopy }: ValueRowProps) {
  return (
    <div className="stack" style={{ gap: 4 }}>
      <span className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {label}
      </span>
      <div className="between" style={{ gap: 8 }}>
        <span className="ltr" style={{ fontWeight: 600, wordBreak: "break-all" }}>
          {value}
        </span>
        <button
          type="button"
          className="icon-btn"
          onClick={() => onCopy(value)}
          aria-label={`${copyLabel}: ${label}`}
          title={copyLabel}
        >
          <Icon name="doc" size={16} />
        </button>
      </div>
    </div>
  );
}
