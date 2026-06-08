"use client";

import { useCallback, useState, type ReactNode } from "react";
import { ImageCropModal } from "./ImageCropModal";

/** Image MIME types the canvas cropper can't handle — bypass it for these. */
const PASSTHROUGH_TYPES = /^image\/(svg\+xml|x-icon|vnd\.microsoft\.icon)$/;

export interface UseImageCropperOptions {
  /** Output aspect ratio (width / height) for the crop frame. */
  aspect: number;
  /** Longest edge of the exported image, in pixels. Defaults to 1280. */
  maxSize?: number;
  /** Encoder quality for lossy formats (0..1). Defaults to 0.9. */
  quality?: number;
  /** Show the 90° rotate control. Defaults to true. */
  allowRotate?: boolean;
}

export interface UseImageCropperResult {
  /**
   * Begin cropping a freshly-picked file. Resolves with the cropped File once
   * the user confirms, or `null` if they cancel. Non-image files resolve
   * immediately, unchanged (callers may still validate type themselves).
   */
  cropFile: (file: File) => Promise<File | null>;
  /** Render this once in the component tree; it hosts the crop modal. */
  cropper: ReactNode;
}

/**
 * Wires "user picked a file → crop/adjust → get a cropped File" into any upload
 * call site, regardless of how that site performs the actual upload. It owns
 * the modal lifecycle; the caller just awaits {@link UseImageCropperResult.cropFile}
 * and uploads whatever File it returns.
 */
export function useImageCropper({
  aspect,
  maxSize = 1280,
  quality = 0.9,
  allowRotate = true,
}: UseImageCropperOptions): UseImageCropperResult {
  const [pending, setPending] = useState<{
    file: File;
    resolve: (file: File | null) => void;
  } | null>(null);

  const cropFile = useCallback((file: File): Promise<File | null> => {
    // Only RASTER images go through the cropper. Non-images and vector/icon
    // formats (SVG, ICO) — which a canvas cropper cannot render (no intrinsic
    // size) — pass straight back unchanged so the upload still works, e.g. an
    // SVG logo or favicon.
    const nonCroppable =
      /^image\/(svg\+xml|x-icon|vnd\.microsoft\.icon)$/.test(file.type);
    if (!file.type.startsWith("image/") || nonCroppable) {
      return Promise.resolve(file);
    }
    return new Promise<File | null>((resolve) => {
      setPending({ file, resolve });
    });
  }, []);

  const settle = useCallback(
    (result: File | null) => {
      pending?.resolve(result);
      setPending(null);
    },
    [pending],
  );

  const cropper = pending ? (
    <ImageCropModal
      file={pending.file}
      aspect={aspect}
      maxSize={maxSize}
      quality={quality}
      allowRotate={allowRotate}
      onCropped={(cropped) => settle(cropped)}
      onCancel={() => settle(null)}
    />
  ) : null;

  return { cropFile, cropper };
}
