"use client";

import { useRef, useState, useTransition, type DragEvent } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { useImageCropper } from "@/components/ui/useImageCropper";
import { useToast } from "@/components/providers/ToastProvider";
import { deletePhoto, uploadPhotos } from "@/lib/actions/owner";
import { cn } from "@/lib/cn";

export interface PhotoManagerProps {
  photos: string[];
}

const ACCEPT = "image/jpeg,image/png,image/webp";
const MAX_FILES = 10;

/** Drag-and-drop multi-photo manager backed by the workspace photo endpoints. */
export function PhotoManager({ photos }: PhotoManagerProps) {
  const t = useTranslations("owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);
  // Workspace photos are landscape → crop each picked image to 4:3 first.
  const { cropFile, cropper } = useImageCropper({ aspect: 4 / 3, maxSize: 1600 });

  const upload = async (files: FileList | null) => {
    if (!files || files.length === 0) return;

    // Crop each picked image in turn; a skipped (cancelled) image is dropped.
    const picked = Array.from(files).slice(0, MAX_FILES);
    const cropped: File[] = [];
    for (const file of picked) {
      const result = await cropFile(file);
      if (result) cropped.push(result);
    }
    if (inputRef.current) inputRef.current.value = "";
    if (cropped.length === 0) return;

    const formData = new FormData();
    cropped.forEach((file) => formData.append("photos[]", file));

    startTransition(async () => {
      const res = await uploadPhotos(formData);
      if (res.ok) {
        toast({ tone: "ok", title: t("settings.photoUploaded") });
      } else {
        toast({ tone: "err", title: t("settings.photoFailed"), body: res.message });
      }
    });
  };

  const remove = (path: string) => {
    startTransition(async () => {
      const res = await deletePhoto(path);
      if (res.ok) {
        toast({ tone: "ok", title: t("settings.photoDeleted") });
      } else {
        toast({ tone: "err", title: t("settings.photoFailed"), body: res.message });
      }
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
        {t("settings.photosHint")}
      </p>

      <label
        className={cn("upload", dragOver && "is-dragover")}
        onDragOver={(e) => {
          e.preventDefault();
          setDragOver(true);
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={onDrop}
      >
        <Icon name="upload" />
        <span style={{ fontWeight: 600, color: "var(--text-2)" }}>
          {t("settings.addPhotos")}
        </span>
        <span style={{ fontSize: "var(--fs-xs)" }}>JPG · PNG · WebP · ≤ 5MB</span>
        <input
          ref={inputRef}
          type="file"
          accept={ACCEPT}
          multiple
          hidden
          disabled={pending}
          onChange={(e) => void upload(e.target.files)}
        />
      </label>

      {photos.length === 0 ? (
        <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
          {t("settings.noPhotos")}
        </p>
      ) : (
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fill, minmax(140px, 1fr))",
            gap: 12,
          }}
        >
          {photos.map((src) => (
            <div
              key={src}
              style={{
                position: "relative",
                aspectRatio: "4 / 3",
                borderRadius: "var(--r-md)",
                overflow: "hidden",
                border: "1px solid var(--border)",
                background: "var(--surface-2)",
              }}
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={src}
                alt=""
                loading="lazy"
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
              />
              <button
                type="button"
                className="icon-btn"
                disabled={pending}
                onClick={() => remove(src)}
                aria-label={t("settings.deletePhoto")}
                style={{
                  position: "absolute",
                  insetInlineEnd: 6,
                  insetBlockStart: 6,
                  background: "var(--surface)",
                  boxShadow: "var(--shadow-sm, 0 1px 3px rgba(0,0,0,.2))",
                }}
              >
                <Icon name="trash" size={16} />
              </button>
            </div>
          ))}
        </div>
      )}

      <Button
        variant="secondary"
        icon="upload"
        loading={pending}
        onClick={() => inputRef.current?.click()}
        style={{ alignSelf: "flex-start" }}
      >
        {t("settings.addPhotos")}
      </Button>
    </div>
  );
}
