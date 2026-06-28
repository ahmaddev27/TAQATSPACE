"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Modal } from "@/components/ui/Modal";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { useToast } from "@/components/providers/ToastProvider";
import {
  provisionInternet,
  type InternetCredentials,
} from "@/lib/actions/owner";

export interface ProvisionInternetButtonProps {
  subscriptionId: string;
  memberName: string;
  /** True when the member already has internet credentials (regenerate flow). */
  hasCredentials: boolean;
}

/**
 * Owner action: issue (or regenerate) the member's internet username/password
 * and send it by email + SMS. On success a modal shows the credentials and which
 * channels were delivered, so the owner can also share them directly.
 */
export function ProvisionInternetButton({
  subscriptionId,
  memberName,
  hasCredentials,
}: ProvisionInternetButtonProps) {
  const t = useTranslations("owner.subscriptions.internet");
  const confirm = useConfirm();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [result, setResult] = useState<InternetCredentials | null>(null);

  const run = () => {
    startTransition(async () => {
      const res = await provisionInternet(subscriptionId);
      if (res.ok && res.data) {
        setResult(res.data);
      } else {
        toast({ tone: "err", title: t("failed"), body: res.message });
      }
    });
  };

  const onClick = async () => {
    if (hasCredentials) {
      const ok = await confirm({
        title: t("regenTitle"),
        message: t("regenMessage", { name: memberName }),
        confirmLabel: t("regenConfirm"),
        tone: "danger",
        icon: "wifi",
      });
      if (!ok) return;
    }
    run();
  };

  const copy = (value: string) => {
    void navigator.clipboard?.writeText(value);
    toast({ tone: "ok", title: t("copied") });
  };

  return (
    <>
      <Button
        variant="secondary"
        size="sm"
        icon="wifi"
        loading={pending}
        onClick={(e) => {
          e.stopPropagation();
          void onClick();
        }}
      >
        {hasCredentials ? t("regenerate") : t("create")}
      </Button>

      {result && (
        <Modal
          title={t("title")}
          icon="wifi"
          onClose={() => setResult(null)}
          footer={
            <Button variant="primary" onClick={() => setResult(null)}>
              {t("done")}
            </Button>
          }
        >
          <div className="stack" style={{ gap: 14 }}>
            <CredRow
              label={t("username")}
              value={result.username}
              onCopy={() => copy(result.username)}
              copyLabel={t("copy")}
            />
            <CredRow
              label={t("password")}
              value={result.password}
              onCopy={() => copy(result.password)}
              copyLabel={t("copy")}
            />
            <div className="row" style={{ gap: 8, flexWrap: "wrap", marginTop: 4 }}>
              <span className={`badge ${result.sent_email ? "badge-success" : "badge-neutral"}`}>
                <Icon name="inbox" size={13} />
                {result.sent_email ? t("sentEmail") : t("noEmail")}
              </span>
              <span className={`badge ${result.sent_sms ? "badge-success" : "badge-neutral"}`}>
                <Icon name="send" size={13} />
                {result.sent_sms ? t("sentSms") : t("noSms")}
              </span>
            </div>
            {!result.sent_email && !result.sent_sms && (
              <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                {t("noChannelsHint")}
              </p>
            )}
          </div>
        </Modal>
      )}
    </>
  );
}

function CredRow({
  label,
  value,
  onCopy,
  copyLabel,
}: {
  label: string;
  value: string;
  onCopy: () => void;
  copyLabel: string;
}) {
  return (
    <div className="between" style={{ gap: 12 }}>
      <div>
        <div className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
          {label}
        </div>
        <div className="ltr" style={{ fontWeight: 700, fontSize: "1.05rem", letterSpacing: "0.5px" }}>
          {value}
        </div>
      </div>
      <button
        type="button"
        className="btn btn-ghost btn-sm"
        onClick={onCopy}
        aria-label={copyLabel}
      >
        <Icon name="grid" size={15} />
        {copyLabel}
      </button>
    </div>
  );
}
