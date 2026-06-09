"use client";

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type PointerEvent as ReactPointerEvent,
} from "react";
import { useTranslations } from "next-intl";
import { Modal } from "./Modal";
import { Button } from "./Button";
import { Icon } from "./Icon";
import {
  cropImageToFile,
  type CropArea,
  type CropTransform,
} from "@/lib/image/crop";

export interface ImageCropModalProps {
  /** The original file the user picked. The modal crops a copy of it. */
  file: File;
  /** Output aspect ratio (width / height). 1 = square, 16/9 = landscape. */
  aspect: number;
  /** Longest edge of the exported image, in CSS pixels. Defaults to 1280. */
  maxSize?: number;
  /** JPEG/WebP quality 0..1 (PNG ignores it). Defaults to 0.9. */
  quality?: number;
  /** Allow 90° rotate controls. Defaults to true. */
  allowRotate?: boolean;
  /** Called with the cropped File once the user confirms. */
  onCropped: (file: File) => void;
  /** Called when the user cancels / closes without cropping. */
  onCancel: () => void;
}

const MIN_ZOOM = 1;
const MAX_ZOOM = 4;
const ZOOM_STEP = 0.01;

/**
 * A reusable image crop/adjust dialog. After a file is picked anywhere on the
 * site, render this modal; the user can zoom, pan and (optionally) rotate the
 * image within a fixed-aspect frame, then confirm to export the cropped region
 * as a new File (preserving jpeg/png/webp where possible).
 *
 * Presentational + self-contained: it owns only its viewport transform; the
 * parent owns the picked file and the resulting cropped file. Built on the
 * shared `Modal` primitive, RTL-correct and keyboard accessible (Escape closes).
 */
export function ImageCropModal({
  file,
  aspect,
  maxSize = 1280,
  quality = 0.9,
  allowRotate = true,
  onCropped,
  onCancel,
}: ImageCropModalProps) {
  const t = useTranslations("common.cropper");

  const [image, setImage] = useState<HTMLImageElement | null>(null);
  // The object URL actually rendered in the <img>, kept in state rather than
  // read off `image.src` — that URL can be revoked early (React StrictMode runs
  // the effect's cleanup between its double-invoke), which would blank the box.
  const [objectUrl, setObjectUrl] = useState<string | null>(null);
  const [zoom, setZoom] = useState(1);
  const [rotation, setRotation] = useState(0);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const [frameSize, setFrameSize] = useState({ w: 0, h: 0 });
  const [busy, setBusy] = useState(false);

  const frameRef = useRef<HTMLDivElement | null>(null);
  const drag = useRef<{
    pointerId: number;
    startX: number;
    startY: number;
    baseX: number;
    baseY: number;
  } | null>(null);

  // Load the picked file into an <img> element (and clean up the object URL).
  // State is set inside the async `onload` callback — never synchronously in
  // the effect body — and the transform is reset for the freshly loaded image.
  useEffect(() => {
    const url = URL.createObjectURL(file);
    setObjectUrl(url);
    const img = new window.Image();
    img.onload = () => {
      setImage(img);
      setZoom(1);
      setRotation(0);
      setOffset({ x: 0, y: 0 });
    };
    img.src = url;
    return () => URL.revokeObjectURL(url);
  }, [file]);

  // Capture the frame element and track its measured size in state (via a
  // ResizeObserver) so render/clamp logic never reads a ref during render.
  const setFrameNode = useCallback((node: HTMLDivElement | null) => {
    frameRef.current = node;
    if (!node) return;
    const measure = () =>
      setFrameSize({ w: node.clientWidth, h: node.clientHeight });
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(node);
    cleanupObserver.current = () => observer.disconnect();
  }, []);
  const cleanupObserver = useRef<(() => void) | null>(null);
  useEffect(() => () => cleanupObserver.current?.(), []);

  // The natural image size as seen by the frame, accounting for 90° rotation.
  const oriented = useMemo(() => {
    if (!image) return { w: 0, h: 0 };
    const swap = rotation % 180 !== 0;
    return {
      w: swap ? image.naturalHeight : image.naturalWidth,
      h: swap ? image.naturalWidth : image.naturalHeight,
    };
  }, [image, rotation]);

  /**
   * Clamp the pan offset so the image always covers the crop frame ("cover"
   * fit). Returns the constrained offset for the given zoom.
   */
  const clampOffset = useCallback(
    (next: { x: number; y: number }, currentZoom: number) => {
      const { w: fw, h: fh } = frameSize;
      if (fw === 0 || oriented.w === 0) return next;
      // Base "cover" scale, then the user zoom on top.
      const base = Math.max(fw / oriented.w, fh / oriented.h);
      const scale = base * currentZoom;
      const dispW = oriented.w * scale;
      const dispH = oriented.h * scale;
      const maxX = Math.max(0, (dispW - fw) / 2);
      const maxY = Math.max(0, (dispH - fh) / 2);
      return {
        x: Math.min(maxX, Math.max(-maxX, next.x)),
        y: Math.min(maxY, Math.max(-maxY, next.y)),
      };
    },
    [oriented, frameSize],
  );

  function changeZoom(next: number) {
    setZoom(next);
    // Re-clamp immediately so a zoom-out never uncovers the frame.
    setOffset((o) => clampOffset(o, next));
  }

  function onPointerDown(e: ReactPointerEvent<HTMLDivElement>) {
    e.currentTarget.setPointerCapture(e.pointerId);
    drag.current = {
      pointerId: e.pointerId,
      startX: e.clientX,
      startY: e.clientY,
      baseX: offset.x,
      baseY: offset.y,
    };
  }

  function onPointerMove(e: ReactPointerEvent<HTMLDivElement>) {
    const d = drag.current;
    if (!d || d.pointerId !== e.pointerId) return;
    const next = {
      x: d.baseX + (e.clientX - d.startX),
      y: d.baseY + (e.clientY - d.startY),
    };
    setOffset(clampOffset(next, zoom));
  }

  function endDrag(e: ReactPointerEvent<HTMLDivElement>) {
    if (drag.current?.pointerId === e.pointerId) {
      drag.current = null;
    }
  }

  function rotate() {
    // Re-centre on rotate: the oriented size swaps, so the old pan may no
    // longer cover the frame. Resetting the offset keeps the result valid.
    setRotation((r) => (r + 90) % 360);
    setOffset({ x: 0, y: 0 });
  }

  async function confirm() {
    if (!image || frameSize.w === 0) return;
    setBusy(true);
    try {
      const transform: CropTransform = {
        zoom,
        rotation,
        offsetX: offset.x,
        offsetY: offset.y,
      };
      const area: CropArea = {
        frameWidth: frameSize.w,
        frameHeight: frameSize.h,
      };
      const cropped = await cropImageToFile(file, image, transform, area, {
        maxSize,
        quality,
      });
      onCropped(cropped);
    } finally {
      setBusy(false);
    }
  }

  // The displayed image transform mirrors the export math (cover + zoom + pan).
  const imageStyle = useMemo(() => {
    if (!image || frameSize.w === 0 || oriented.w === 0) {
      return { display: "none" as const };
    }
    const base = Math.max(
      frameSize.w / oriented.w,
      frameSize.h / oriented.h,
    );
    const scale = base * zoom;
    return {
      width: image.naturalWidth * scale,
      height: image.naturalHeight * scale,
      transform: `translate(-50%, -50%) translate(${offset.x}px, ${offset.y}px) rotate(${rotation}deg)`,
    };
  }, [image, oriented, zoom, offset, rotation, frameSize]);

  return (
    <Modal
      title={t("title")}
      onClose={onCancel}
      icon="edit"
      closeLabel={t("cancel")}
      footer={
        <>
          <Button variant="ghost" onClick={onCancel} disabled={busy}>
            {t("cancel")}
          </Button>
          <Button
            variant="primary"
            icon="check"
            onClick={confirm}
            loading={busy}
            disabled={!image}
          >
            {t("apply")}
          </Button>
        </>
      }
    >
      <div className="cropper">
        <div
          ref={setFrameNode}
          className="cropper-frame"
          style={{ aspectRatio: String(aspect) }}
          onPointerDown={onPointerDown}
          onPointerMove={onPointerMove}
          onPointerUp={endDrag}
          onPointerCancel={endDrag}
          role="application"
          aria-label={t("repositionHint")}
        >
          {image && (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={objectUrl ?? image.src}
              alt=""
              draggable={false}
              className="cropper-img"
              style={imageStyle}
            />
          )}
          <span className="cropper-grid" aria-hidden="true" />
        </div>

        <p className="cropper-hint muted-3">{t("repositionHint")}</p>

        <div className="cropper-controls">
          <label className="cropper-zoom">
            <Icon name="search" size={16} />
            <input
              type="range"
              min={MIN_ZOOM}
              max={MAX_ZOOM}
              step={ZOOM_STEP}
              value={zoom}
              onChange={(e) => changeZoom(Number(e.target.value))}
              aria-label={t("zoom")}
              disabled={!image}
            />
          </label>

          {allowRotate && (
            <Button
              variant="secondary"
              size="sm"
              icon="refresh"
              onClick={rotate}
              disabled={!image}
            >
              {t("rotate")}
            </Button>
          )}
        </div>
      </div>
    </Modal>
  );
}
