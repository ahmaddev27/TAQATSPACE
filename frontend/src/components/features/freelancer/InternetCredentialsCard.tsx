"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { useToast } from "@/components/providers/ToastProvider";

export interface InternetCredentialsCardProps {
  username: string;
  password: string | null;
}

/**
 * Shows the member their own internet login on the dashboard: the username is
 * always visible; the password stays masked until they reveal it. Both can be
 * copied.
 */
export function InternetCredentialsCard({
  username,
  password,
}: InternetCredentialsCardProps) {
  const t = useTranslations("freelancer.home.internet");
  const { toast } = useToast();
  const [revealed, setRevealed] = useState(false);

  const copy = (value: string) => {
    void navigator.clipboard?.writeText(value);
    toast({ tone: "ok", title: t("copied") });
  };

  return (
    <div className="card card-pad stack" style={{ gap: 14 }}>
      <div className="row" style={{ gap: 10 }}>
        <span className="st-ico">
          <Icon name="wifi" size={18} />
        </span>
        <div>
          <h3 className="h3">{t("title")}</h3>
          <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
            {t("subtitle")}
          </p>
        </div>
      </div>

      <div className="dash-2col" style={{ gap: 12 }}>
        <Cred
          label={t("username")}
          value={username}
          onCopy={() => copy(username)}
          copyLabel={t("copy")}
        />
        {password && (
          <Cred
            label={t("password")}
            value={revealed ? password : "••••••••"}
            mono={revealed}
            onCopy={() => copy(password)}
            copyLabel={t("copy")}
            toggle={{
              label: revealed ? t("hide") : t("reveal"),
              icon: revealed ? "x" : "eye",
              onClick: () => setRevealed((v) => !v),
            }}
          />
        )}
      </div>
    </div>
  );
}

function Cred({
  label,
  value,
  mono = true,
  onCopy,
  copyLabel,
  toggle,
}: {
  label: string;
  value: string;
  mono?: boolean;
  onCopy: () => void;
  copyLabel: string;
  toggle?: { label: string; icon: "eye" | "x"; onClick: () => void };
}) {
  return (
    <div
      className="stack"
      style={{
        gap: 6,
        padding: "12px 14px",
        border: "1px solid var(--border)",
        borderRadius: "var(--r-md)",
        background: "var(--surface-2)",
      }}
    >
      <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
        {label}
      </span>
      <div className="between" style={{ gap: 8 }}>
        <span
          className={mono ? "ltr" : "ltr"}
          style={{
            fontWeight: 700,
            fontSize: "1.05rem",
            letterSpacing: mono ? "1px" : "0.5px",
          }}
        >
          {value}
        </span>
        <span className="row" style={{ gap: 4 }}>
          {toggle && (
            <button
              type="button"
              className="icon-btn"
              onClick={toggle.onClick}
              aria-label={toggle.label}
              title={toggle.label}
            >
              <Icon name={toggle.icon} size={16} />
            </button>
          )}
          <button
            type="button"
            className="icon-btn"
            onClick={onCopy}
            aria-label={copyLabel}
            title={copyLabel}
          >
            <Icon name="grid" size={16} />
          </button>
        </span>
      </div>
    </div>
  );
}
