"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { resetWorkspace } from "@/lib/actions/owner";

export interface DangerTabProps {
  slug: string;
}

/** "Danger zone" tab: reset the workspace after a warning, a typed slug and a 2FA code. */
export function DangerTab({ slug }: DangerTabProps) {
  const t = useTranslations("owner.settings.danger");
  const confirm = useConfirm();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [open, setOpen] = useState(false);
  const [confirmation, setConfirmation] = useState("");
  const [reason, setReason] = useState("");
  const [code, setCode] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const close = () => {
    setOpen(false);
    setConfirmation("");
    setReason("");
    setCode("");
    setErrors({});
  };

  const start = async () => {
    const proceed = await confirm({
      title: t("warnTitle"),
      message: t("warnMessage"),
      confirmLabel: t("warnContinue"),
      tone: "danger",
      icon: "alert",
    });
    if (proceed) setOpen(true);
  };

  const submit = () =>
    startTransition(async () => {
      const res = await resetWorkspace({ confirmation, reason: reason.trim(), code });
      if (res.ok) {
        toast({ tone: "ok", title: t("done") });
        close();
        return;
      }
      setErrors(res.errors ?? {});
      toast({ tone: "err", title: t("failed"), body: res.message });
    });

  const ready = confirmation === slug && reason.trim().length >= 3;

  return (
    <div className="stack" style={{ gap: 16 }}>
      <h4 className="h4">{t("title")}</h4>
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {t("description")}
      </p>
      <Button variant="danger" icon="alert" onClick={start} style={{ alignSelf: "flex-start" }}>
        {t("reset")}
      </Button>

      {open && (
        <Modal
          title={t("modalTitle")}
          icon="alert"
          onClose={pending ? () => undefined : close}
          footer={
            <>
              <Button variant="ghost" onClick={close} disabled={pending}>
                {t("cancel")}
              </Button>
              <Button variant="danger" loading={pending} disabled={!ready} onClick={submit}>
                {t("confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 14 }}>
            <Field label={t("confirmationLabel", { slug })} error={errors.confirmation?.[0]}>
              <Input
                className="ltr"
                value={confirmation}
                onChange={(e) => setConfirmation(e.target.value)}
                autoComplete="off"
              />
            </Field>
            <Field label={t("reasonLabel")} error={errors.reason?.[0]}>
              <Textarea
                rows={3}
                maxLength={500}
                value={reason}
                onChange={(e) => setReason(e.target.value)}
              />
            </Field>
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
        </Modal>
      )}
    </div>
  );
}
