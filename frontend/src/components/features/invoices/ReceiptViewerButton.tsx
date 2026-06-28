"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Modal } from "@/components/ui/Modal";
import { Icon, type IconName } from "@/components/ui/Icon";

export interface ReceiptViewerButtonProps {
  /** Resolved URL of the receipt file (image or PDF). */
  url: string;
  /** Trigger button label. */
  label: string;
  /** Trigger button icon. Defaults to an eye. */
  icon?: IconName;
}

/** True when the URL points at a PDF (vs an image we can render inline). */
function isPdf(url: string): boolean {
  return /\.pdf(\?|#|$)/i.test(url);
}

/**
 * Opens a payment receipt in an in-app modal instead of navigating away to an
 * external page. Images render inline; PDFs embed in a frame. The original is
 * always reachable via the footer link.
 */
export function ReceiptViewerButton({
  url,
  label,
  icon = "eye",
}: ReceiptViewerButtonProps) {
  const t = useTranslations("common.receipt");
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        type="button"
        className="btn btn-ghost btn-sm"
        onClick={() => setOpen(true)}
      >
        <Icon name={icon} size={15} />
        {label}
      </button>

      {open && (
        <Modal
          title={t("title")}
          icon="receipt"
          onClose={() => setOpen(false)}
          footer={
            <a
              className="btn btn-secondary btn-sm"
              href={url}
              target="_blank"
              rel="noopener noreferrer"
            >
              <Icon name="download" size={15} />
              {t("openOriginal")}
            </a>
          }
        >
          <div
            style={{
              display: "grid",
              placeItems: "center",
              background: "var(--surface-2)",
              borderRadius: "var(--r-md)",
              padding: 8,
              maxHeight: "70vh",
              overflow: "auto",
            }}
          >
            {isPdf(url) ? (
              <iframe
                src={url}
                title={t("title")}
                style={{ width: "100%", height: "70vh", border: "none" }}
              />
            ) : (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={url}
                alt={t("title")}
                style={{ maxWidth: "100%", maxHeight: "70vh", objectFit: "contain" }}
              />
            )}
          </div>
        </Modal>
      )}
    </>
  );
}
