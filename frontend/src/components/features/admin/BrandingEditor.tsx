"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { FileDropzone } from "@/components/ui/FileDropzone";
import { Input } from "@/components/ui/Input";
import { Textarea } from "@/components/ui/Textarea";
import { useImageCropper } from "@/components/ui/useImageCropper";
import { useToast } from "@/components/providers/ToastProvider";
import { updateBranding, uploadBrandingImage } from "@/lib/actions/admin";
import type { Branding } from "@/lib/types";

/**
 * A brand image slot: the canonical storage `path` (round-tripped on save) and
 * a resolved `url` for the preview. Both empty means "use the built-in default".
 */
interface ImageState {
  path: string;
  url: string;
}

/** Which brand asset is being uploaded, so its control can show a pending state. */
type ImageSlot = "favicon" | "logoLight" | "logoDark";

interface FormState {
  siteName: string;
  metaTitle: string;
  metaDescription: string;
  favicon: ImageState;
  logoLight: ImageState;
  logoDark: ImageState;
}

const emptyImage = (): ImageState => ({ path: "", url: "" });

function toImage(path?: string, url?: string): ImageState {
  return { path: path ?? "", url: url ?? "" };
}

function buildInitial(b: Branding): FormState {
  return {
    siteName: b.site_name ?? "",
    metaTitle: b.meta_title ?? "",
    metaDescription: b.meta_description ?? "",
    favicon: toImage(b.favicon, b.faviconUrl),
    logoLight: toImage(b.logo_light, b.logo_lightUrl),
    logoDark: toImage(b.logo_dark, b.logo_darkUrl),
  };
}

/** The stored path, or `undefined` when the slot is empty (built-in default). */
function imagePath(image: ImageState): string | undefined {
  const path = image.path.trim();
  return path ? path : undefined;
}

/** A trimmed string, or `undefined` when empty. */
function clean(value: string): string | undefined {
  const trimmed = value.trim();
  return trimmed ? trimmed : undefined;
}

function buildPayload(form: FormState): Branding {
  return {
    site_name: clean(form.siteName),
    meta_title: clean(form.metaTitle),
    meta_description: clean(form.metaDescription),
    favicon: imagePath(form.favicon),
    logo_light: imagePath(form.logoLight),
    logo_dark: imagePath(form.logoDark),
  };
}

/* -------------------------------------------------------------------------- */
/*  Brand image control — crop, preview, replace, remove                       */
/* -------------------------------------------------------------------------- */

interface BrandImageLabels {
  label: string;
  hint: string;
  upload: string;
  replace: string;
  remove: string;
}

interface BrandImageFieldProps {
  value: ImageState;
  onSelect: (file: File | null) => void;
  uploading: boolean;
  /** Crop aspect ratio (1 for favicon, wide for the logos). */
  aspect: number;
  /** Render the preview against a dark backdrop (for the dark-mode logo). */
  dark?: boolean;
  labels: BrandImageLabels;
}

/**
 * A single brand-asset control: pick → crop → preview, with Replace + Remove.
 * Clearing (Remove) drops the override so the app falls back to its built-in
 * logo/favicon. Presentational — the parent owns the upload + state.
 */
function BrandImageField({
  value,
  onSelect,
  uploading,
  aspect,
  dark,
  labels,
}: BrandImageFieldProps) {
  const hasImage = value.url.trim() !== "";
  const { cropFile, cropper } = useImageCropper({ aspect, maxSize: 512 });

  const handleSelect = async (file: File | null) => {
    if (file === null) {
      onSelect(null);
      return;
    }
    const cropped = await cropFile(file);
    if (cropped) onSelect(cropped);
  };

  return (
    <div className="stack" style={{ gap: 8 }}>
      {cropper}
      <span className="label">{labels.label}</span>
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {labels.hint}
      </p>
      {hasImage && (
        <div className="stack" style={{ gap: 8 }}>
          <div
            style={{
              display: "inline-flex",
              alignItems: "center",
              justifyContent: "center",
              padding: 14,
              maxWidth: 260,
              borderRadius: "var(--r-lg)",
              border: "1px solid var(--line)",
              background: dark ? "#0c1116" : "var(--surface)",
            }}
          >
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={value.url}
              alt=""
              style={{ height: 48, width: "auto", display: "block" }}
            />
          </div>
          <Button
            variant="ghost"
            icon="trash"
            onClick={() => onSelect(null)}
            disabled={uploading}
            style={{ alignSelf: "flex-start" }}
          >
            {labels.remove}
          </Button>
        </div>
      )}
      <FileDropzone
        value={null}
        onChange={handleSelect}
        accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml"
        label={hasImage ? labels.replace : labels.upload}
        disabled={uploading}
      />
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Editor                                                                     */
/* -------------------------------------------------------------------------- */

export interface BrandingEditorProps {
  initial: Branding;
}

export function BrandingEditor({ initial }: BrandingEditorProps) {
  const t = useTranslations("admin.rm.branding");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [form, setForm] = useState<FormState>(() => buildInitial(initial));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [uploading, setUploading] = useState<ImageSlot | null>(null);

  const setSlotImage = (slot: ImageSlot, image: ImageState) =>
    setForm((f) => ({ ...f, [slot]: image }));

  // Upload a cropped asset, then store its path (save) + url (preview). A null
  // file clears the slot (falls back to the built-in default). Failures toast.
  const uploadImage = (slot: ImageSlot, file: File | null) => {
    if (file === null) {
      setSlotImage(slot, emptyImage());
      return;
    }
    setUploading(slot);
    startTransition(async () => {
      const res = await uploadBrandingImage(file);
      setUploading(null);
      if (res.ok) {
        setSlotImage(slot, { path: res.data.path, url: res.data.url });
      } else {
        toast({ tone: "err", title: t("uploadFailed"), body: res.message });
      }
    });
  };

  const save = () => {
    startTransition(async () => {
      const res = await updateBranding(buildPayload(form));
      if (res.ok) {
        setErrors({});
        toast({ tone: "ok", title: t("saved") });
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("saveFailed"), body: res.message });
      }
    });
  };

  const logoLabels: Omit<BrandImageLabels, "label"> = {
    hint: t("logo.hint"),
    upload: t("logo.upload"),
    replace: t("logo.replace"),
    remove: t("logo.remove"),
  };
  const faviconLabels: BrandImageLabels = {
    label: t("favicon.label"),
    hint: t("favicon.hint"),
    upload: t("favicon.upload"),
    replace: t("logo.replace"),
    remove: t("logo.remove"),
  };

  return (
    <div className="card card-pad stack" style={{ gap: 18 }}>
      <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {t("hint")}
      </p>

      <Field label={t("siteName")}>
        <Input
          value={form.siteName}
          onChange={(e) => setForm((f) => ({ ...f, siteName: e.target.value }))}
        />
      </Field>
      <Field label={t("metaTitle")}>
        <Input
          value={form.metaTitle}
          onChange={(e) => setForm((f) => ({ ...f, metaTitle: e.target.value }))}
        />
      </Field>
      <Field label={t("metaDescription")}>
        <Textarea
          rows={3}
          value={form.metaDescription}
          onChange={(e) =>
            setForm((f) => ({ ...f, metaDescription: e.target.value }))
          }
        />
      </Field>

      <BrandImageField
        labels={faviconLabels}
        value={form.favicon}
        uploading={uploading === "favicon"}
        aspect={1}
        onSelect={(file) => uploadImage("favicon", file)}
      />
      <BrandImageField
        labels={{ label: t("logo.labelLight"), ...logoLabels }}
        value={form.logoLight}
        uploading={uploading === "logoLight"}
        aspect={3 / 1}
        onSelect={(file) => uploadImage("logoLight", file)}
      />
      <BrandImageField
        labels={{ label: t("logo.labelDark"), ...logoLabels }}
        value={form.logoDark}
        uploading={uploading === "logoDark"}
        aspect={3 / 1}
        dark
        onSelect={(file) => uploadImage("logoDark", file)}
      />

      {Object.keys(errors).length > 0 && (
        <div className="field is-error stack" style={{ gap: 4 }}>
          {Object.values(errors)
            .flat()
            .map((message) => (
              <span key={message} className="hint">
                {message}
              </span>
            ))}
        </div>
      )}

      <Button
        variant="primary"
        icon="check"
        loading={pending}
        onClick={save}
        style={{ alignSelf: "flex-start" }}
      >
        {pending ? t("saving") : t("save")}
      </Button>
    </div>
  );
}
