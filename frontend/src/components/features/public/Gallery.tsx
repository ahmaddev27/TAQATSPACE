"use client";

import { useState } from "react";

export interface GalleryProps {
  photos: string[];
  alt: string;
}

const PLACEHOLDER = "/images/workspaces/placeholder-1.svg";

interface PhotoSlotProps {
  src: string;
  alt: string;
  h: number;
  className?: string;
}

/** A photo that falls back to an on-brand placeholder if it fails to load. */
function PhotoSlot({ src, alt, h, className }: PhotoSlotProps) {
  const [failed, setFailed] = useState(false);
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={failed ? PLACEHOLDER : src}
      alt={alt}
      onError={() => {
        if (!failed) setFailed(true);
      }}
      className={className}
      style={{
        height: h,
        width: "100%",
        objectFit: "cover",
        borderRadius: "var(--r-lg)",
        display: "block",
      }}
    />
  );
}

/**
 * Workspace gallery that adapts to the number of real photos — it never pads
 * empty slots with placeholders (so one uploaded photo shows alone, not beside
 * two fake ones). A single placeholder is shown only when there are no photos.
 */
export function Gallery({ photos, alt }: GalleryProps) {
  const real = photos.filter(Boolean);

  if (real.length === 0) {
    return (
      <div className="gallery gallery-1">
        <PhotoSlot src={PLACEHOLDER} alt={alt} h={320} />
      </div>
    );
  }

  if (real.length === 1) {
    return (
      <div className="gallery gallery-1">
        <PhotoSlot src={real[0]} alt={alt} h={320} />
      </div>
    );
  }

  if (real.length === 2) {
    return (
      <div className="gallery gallery-2">
        <PhotoSlot src={real[0]} alt={alt} h={300} />
        <PhotoSlot src={real[1]} alt={alt} h={300} />
      </div>
    );
  }

  return (
    <div className="gallery">
      <PhotoSlot src={real[0]} alt={alt} h={320} className="gallery-main" />
      <div className="gallery-side">
        <PhotoSlot src={real[1]} alt={alt} h={152} />
        <PhotoSlot src={real[2]} alt={alt} h={152} />
      </div>
    </div>
  );
}
