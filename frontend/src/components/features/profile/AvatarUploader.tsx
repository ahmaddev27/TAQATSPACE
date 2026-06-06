"use client";

import { useRef, useState, useEffect } from "react";
import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";

const MAX_SIZE = 5 * 1024 * 1024; // 5MB
const ACCEPTED = ["image/png", "image/jpeg", "image/jpg", "image/webp"];

export interface AvatarUploaderProps {
  /** Current avatar URL from the backend (or null). */
  initialUrl: string | null;
  /** First grapheme of the user's name, shown when there is no image. */
  fallbackInitial: string;
  /** Bubble the chosen File (or null when cleared) up to the form. */
  onChange: (file: File | null) => void;
}

/**
 * Circular avatar with an inline file picker. Validates type/size client-side
 * and previews the selection via an object URL; the actual upload happens when
 * the parent form submits (multipart Server Action).
 */
export function AvatarUploader({
  initialUrl,
  fallbackInitial,
  onChange,
}: AvatarUploaderProps) {
  const t = useTranslations("profile");
  const tErr = useTranslations("profile.errors");
  const inputRef = useRef<HTMLInputElement>(null);
  const [preview, setPreview] = useState<string | null>(initialUrl);
  const [error, setError] = useState<string | null>(null);
  const objectUrl = useRef<string | null>(null);

  // Revoke the object URL on unmount / replacement to avoid a memory leak.
  useEffect(() => {
    return () => {
      if (objectUrl.current) URL.revokeObjectURL(objectUrl.current);
    };
  }, []);

  function pick() {
    inputRef.current?.click();
  }

  function handleFile(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = ""; // allow re-selecting the same file
    if (!file) return;

    if (!ACCEPTED.includes(file.type)) {
      setError(tErr("fileType"));
      return;
    }
    if (file.size > MAX_SIZE) {
      setError(tErr("fileSize"));
      return;
    }

    setError(null);
    if (objectUrl.current) URL.revokeObjectURL(objectUrl.current);
    const url = URL.createObjectURL(file);
    objectUrl.current = url;
    setPreview(url);
    onChange(file);
  }

  function clear() {
    if (objectUrl.current) {
      URL.revokeObjectURL(objectUrl.current);
      objectUrl.current = null;
    }
    setPreview(initialUrl);
    setError(null);
    onChange(null);
  }

  return (
    <div className="row" style={{ gap: 18, alignItems: "center" }}>
      <input
        ref={inputRef}
        type="file"
        accept="image/png,image/jpeg,image/webp"
        onChange={handleFile}
        style={{ display: "none" }}
        aria-hidden="true"
        tabIndex={-1}
      />

      <span
        className="avatar lg round"
        style={{
          width: 88,
          height: 88,
          fontSize: "2rem",
          overflow: "hidden",
          flex: "none",
        }}
      >
        {preview ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={preview}
            alt=""
            style={{ width: "100%", height: "100%", objectFit: "cover" }}
          />
        ) : (
          fallbackInitial
        )}
      </span>

      <div className="stack" style={{ gap: 8 }}>
        <div className="h3">{t("avatarTitle")}</div>
        <div className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
          {t("avatarHint")}
        </div>
        <div className="row" style={{ gap: 8 }}>
          <Button variant="secondary" size="sm" icon="upload" onClick={pick}>
            {t("changeAvatar")}
          </Button>
          {preview && preview !== initialUrl && (
            <Button variant="ghost" size="sm" icon="x" onClick={clear}>
              {t("removeAvatar")}
            </Button>
          )}
        </div>
        {error && (
          <span className="hint" style={{ color: "var(--danger)" }}>
            <Icon name="alert" size={13} /> {error}
          </span>
        )}
      </div>
    </div>
  );
}
