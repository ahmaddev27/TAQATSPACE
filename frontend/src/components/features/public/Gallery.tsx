"use client";

import { useState } from "react";

export interface GalleryProps {
  photos: string[];
  alt: string;
}

const PLACEHOLDERS = [
  "/images/workspaces/placeholder-1.svg",
  "/images/workspaces/placeholder-2.svg",
  "/images/workspaces/placeholder-3.svg",
] as const;

interface PhotoSlotProps {
  src: string | undefined;
  fallbackSrc: string;
  alt: string;
  h: number;
  className?: string;
}

/** A photo slot that falls back to an on-brand placeholder when no real photo. */
function PhotoSlot({ src, fallbackSrc, alt, h, className }: PhotoSlotProps) {
  const [failed, setFailed] = useState(false);
  const url = !src || failed ? fallbackSrc : src;
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={url}
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

/** Workspace photo gallery (1 main + 2 side), ported from the prototype layout. */
export function Gallery({ photos, alt }: GalleryProps) {
  return (
    <div className="gallery">
      <PhotoSlot src={photos[0]} fallbackSrc={PLACEHOLDERS[0]} alt={alt} h={320} className="gallery-main" />
      <div className="gallery-side">
        <PhotoSlot src={photos[1]} fallbackSrc={PLACEHOLDERS[1]} alt={alt} h={152} />
        <PhotoSlot src={photos[2]} fallbackSrc={PLACEHOLDERS[2]} alt={alt} h={152} />
      </div>
    </div>
  );
}
