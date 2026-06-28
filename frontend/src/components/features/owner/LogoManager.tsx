"use client";

import { useRef, useState, useTransition, type DragEvent } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { useImageCropper } from "@/components/ui/useImageCropper";
import { useToast } from "@/components/providers/ToastProvider";
import { deleteLogo, uploadLogo } from "@/lib/actions/owner";
import { cn } from "@/lib/cn";

export interface LogoManagerProps {
  logo: string | null;
  hasLogo: boolean;
}

const ACCEPT = "image/jpeg,image/png,image/webp";

/**
 * Single-image manager for the workspace logo. The logo represents the space
 * across the app (chat, invoices, member dashboards) — square crop, one image.
 */
export function LogoManager({ logo, hasLogo }: LogoManagerProps) {
  const t = useTranslations("owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);
  // No forced aspect — keep the logo's own ratio (full width); the owner can
  // still reposition/zoom. Capped to a reasonable export size.
  const { cropFile, cropper } = useImageCropper({ maxSize: 640 });

  const upload = async (files: FileList | null) => {
    if (!files || files.length === 0) return;

    const cropped = await cropFile(files[0]);
    if (inputRef.current) inputRef.current.value = "";
    if (!cropped) return;

    const formData = new FormData();
    formData.append("logo", cropped);

    startTransition(async () => {
      const res = await uploadLogo(formData);
      toast(
        res.ok
          ? { tone: "ok", title: t("settings.logoUploaded") }
          : { tone: "err", title: t("settings.logoFailed"), body: res.message },
      );
    });
  };

  const remove = () => {
    startTransition(async () => {
      const res = await deleteLogo();
      toast(
        res.ok
          ? { tone: "ok", title: t("settings.logoRemoved") }
          : { tone: "err", title: t("settings.logoFailed"), body: res.message },
      );
    });
  };

  const onDrop = (e: DragEvent<HTMLLabelElement>) => {
    e.preventDefault();
    setDragOver(false);
    void upload(e.dataTransfer.files);
  };

  return (
    <div className="stack" style={{ gap: 16 }}>
      {cropper}
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {t("settings.logoHint")}
      </p>

      <div style={{ display: "flex", alignItems: "center", gap: 18, flexWrap: "wrap" }}>
        {logo ? (
          <div
            style={{
              width: 96,
              height: 96,
              borderRadius: "var(--r-md)",
              overflow: "hidden",
              border: "1px solid var(--border)",
              background: "var(--surface-2)",
              flexShrink: 0,
            }}
          >
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={logo}
              alt=""
              style={{ width: "100%", height: "100%", objectFit: "contain" }}
            />
          </div>
        ) : (
          <div
            style={{
              width: 96,
              height: 96,
              borderRadius: "var(--r-md)",
              border: "1px dashed var(--border)",
              display: "grid",
              placeItems: "center",
              color: "var(--text-3)",
              flexShrink: 0,
            }}
          >
            <Icon name="building" />
          </div>
        )}

        <label
          className={cn("upload", dragOver && "is-dragover")}
          style={{ flex: 1, minWidth: 200 }}
          onDragOver={(e) => {
            e.preventDefault();
            setDragOver(true);
          }}
          onDragLeave={() => setDragOver(false)}
          onDrop={onDrop}
        >
          <Icon name="upload" />
          <span style={{ fontWeight: 600, color: "var(--text-2)" }}>
            {t("settings.uploadLogo")}
          </span>
          <span style={{ fontSize: "var(--fs-xs)" }}>JPG · PNG · WebP · ≤ 5MB</span>
          <input
            ref={inputRef}
            type="file"
            accept={ACCEPT}
            hidden
            disabled={pending}
            onChange={(e) => void upload(e.target.files)}
          />
        </label>
      </div>

      <div style={{ display: "flex", gap: 10 }}>
        <Button
          variant="secondary"
          icon="upload"
          loading={pending}
          onClick={() => inputRef.current?.click()}
        >
          {t("settings.uploadLogo")}
        </Button>
        {hasLogo && (
          <Button variant="ghost" icon="trash" disabled={pending} onClick={remove}>
            {t("settings.removeLogo")}
          </Button>
        )}
      </div>
    </div>
  );
}
