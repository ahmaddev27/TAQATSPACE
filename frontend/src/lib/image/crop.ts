/**
 * Canvas-based image cropping. Mirrors the on-screen viewport transform
 * (a "cover" fit plus user zoom, pan and 90° rotation) so the exported region
 * is exactly what the user framed in {@link ImageCropModal}.
 *
 * Kept framework-agnostic and side-effect-free so it can be unit-reasoned and
 * reused by any call site that already has a loaded `HTMLImageElement`.
 */

/** The viewport transform the user applied in the crop frame. */
export interface CropTransform {
  /** User zoom on top of the base cover scale (1 = no extra zoom). */
  zoom: number;
  /** Rotation in degrees, one of 0 / 90 / 180 / 270. */
  rotation: number;
  /** Horizontal pan offset, in CSS pixels (frame-space). */
  offsetX: number;
  /** Vertical pan offset, in CSS pixels (frame-space). */
  offsetY: number;
}

/** The on-screen crop frame size, in CSS pixels. */
export interface CropArea {
  frameWidth: number;
  frameHeight: number;
}

export interface CropOutputOptions {
  /** Longest edge of the exported image, in pixels. */
  maxSize: number;
  /** Encoder quality for lossy formats (0..1). */
  quality: number;
}

/** Output MIME types we can re-encode to; anything else falls back to JPEG. */
const SUPPORTED_OUTPUT = new Set(["image/jpeg", "image/png", "image/webp"]);

/** Pick a safe output MIME for the canvas encoder, preserving the source type. */
function resolveOutputType(sourceType: string): string {
  return SUPPORTED_OUTPUT.has(sourceType) ? sourceType : "image/jpeg";
}

/** Swap a file's extension to match the (possibly changed) output type. */
function renameForType(name: string, type: string): string {
  const ext = type === "image/png" ? "png" : type === "image/webp" ? "webp" : "jpg";
  const base = name.replace(/\.[^./\\]+$/, "");
  return `${base}.${ext}`;
}

/**
 * Crop `image` to the framed region and return it as a new {@link File}.
 *
 * The export reproduces the screen math: the image is laid out with a "cover"
 * scale (so it always fills the frame), then the user's `zoom` and `offset`
 * are applied, then `rotation`. We compute, in source-pixel space, the region
 * of the original image that maps onto the frame and draw it onto an output
 * canvas sized to the frame's aspect ratio (capped at `maxSize`).
 */
export async function cropImageToFile(
  source: File,
  image: HTMLImageElement,
  transform: CropTransform,
  area: CropArea,
  options: CropOutputOptions,
): Promise<File> {
  const { zoom, rotation, offsetX, offsetY } = transform;
  const { frameWidth, frameHeight } = area;

  const swap = rotation % 180 !== 0;
  // Oriented natural size (post-rotation) drives the cover fit, matching the UI.
  const orientedW = swap ? image.naturalHeight : image.naturalWidth;
  const orientedH = swap ? image.naturalWidth : image.naturalHeight;

  const base = Math.max(frameWidth / orientedW, frameHeight / orientedH);
  const scale = base * zoom;

  // Output canvas keeps the frame's aspect. Target `maxSize` on the long edge,
  // but never upscale past the detail the source actually supplies at the
  // current zoom — `1 / scale` source-px map to one frame-px, so the frame's
  // long edge is backed by at most `longEdge / scale` source pixels.
  const longEdge = Math.max(frameWidth, frameHeight);
  const sourceBackedLongEdge = longEdge / scale;
  const exportScale = Math.min(options.maxSize, sourceBackedLongEdge) / longEdge;
  const outW = Math.max(1, Math.round(frameWidth * exportScale));
  const outH = Math.max(1, Math.round(frameHeight * exportScale));

  const canvas = document.createElement("canvas");
  canvas.width = outW;
  canvas.height = outH;
  const ctx = canvas.getContext("2d");
  if (!ctx) {
    throw new Error("Canvas 2D context unavailable");
  }

  ctx.imageSmoothingQuality = "high";

  // Map frame-space → output-space. The frame centre is the canvas centre.
  const k = outW / frameWidth; // == outH / frameHeight (same aspect)

  ctx.save();
  // Move to canvas centre, apply pan (scaled), rotation, then draw the image
  // centred at its scaled size — identical sequence to the CSS transform.
  ctx.translate(outW / 2 + offsetX * k, outH / 2 + offsetY * k);
  ctx.rotate((rotation * Math.PI) / 180);
  const drawW = image.naturalWidth * scale * k;
  const drawH = image.naturalHeight * scale * k;
  ctx.drawImage(image, -drawW / 2, -drawH / 2, drawW, drawH);
  ctx.restore();

  const type = resolveOutputType(source.type);
  const blob = await canvasToBlob(canvas, type, options.quality);
  return new File([blob], renameForType(source.name, type), {
    type,
    lastModified: Date.now(),
  });
}

/** Promisified `canvas.toBlob`, rejecting if the browser yields no blob. */
function canvasToBlob(
  canvas: HTMLCanvasElement,
  type: string,
  quality: number,
): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => (blob ? resolve(blob) : reject(new Error("toBlob returned null"))),
      type,
      quality,
    );
  });
}
