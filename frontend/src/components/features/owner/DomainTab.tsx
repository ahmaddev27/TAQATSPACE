"use client";

import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { useToast } from "@/components/providers/ToastProvider";

export interface DomainTabProps {
  slug: string;
  code: string;
  dashboardUrl: string;
  publicUrl: string;
}

interface LinkRowProps {
  label: string;
  value: string;
  copyLabel: string;
  onCopy: (value: string) => void;
}

function LinkRow({ label, value, copyLabel, onCopy }: LinkRowProps) {
  return (
    <div className="stack" style={{ gap: 4 }}>
      <span className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {label}
      </span>
      <div className="between" style={{ gap: 8 }}>
        <span className="ltr" style={{ fontWeight: 600 }}>
          {value}
        </span>
        <button
          type="button"
          className="icon-btn"
          onClick={() => onCopy(value)}
          aria-label={`${copyLabel}: ${label}`}
          title={copyLabel}
        >
          <Icon name="grid" size={16} />
        </button>
      </div>
    </div>
  );
}

/** Read-only "Domain & DNS" tab: the workspace's links and identity. */
export function DomainTab({ slug, code, dashboardUrl, publicUrl }: DomainTabProps) {
  const t = useTranslations("owner.settings");
  const { toast } = useToast();

  const copy = (value: string) => {
    void navigator.clipboard?.writeText(value);
    toast({ tone: "ok", title: t("domainCopied") });
  };

  return (
    <div className="stack" style={{ gap: 16 }}>
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {t("domainHint")}
      </p>
      <LinkRow label={t("domainDashboard")} value={dashboardUrl} copyLabel={t("domainCopy")} onCopy={copy} />
      <LinkRow label={t("domainPublic")} value={publicUrl} copyLabel={t("domainCopy")} onCopy={copy} />
      <LinkRow label={t("domainSlug")} value={slug} copyLabel={t("domainCopy")} onCopy={copy} />
      <LinkRow label={t("domainCode")} value={code} copyLabel={t("domainCopy")} onCopy={copy} />
    </div>
  );
}
