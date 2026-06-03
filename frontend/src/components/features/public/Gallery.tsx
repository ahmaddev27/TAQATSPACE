"use client";

import { useState } from "react";
import { ImgPlaceholder } from "@/components/ui/ImgPlaceholder";

export interface GalleryProps {
  photos: string[];
  alt: string;
}

interface PhotoSlotProps {
  src: string | undefined;
  alt: string;
  h: number;
  color: string;
  className?: string;
}

/** A photo slot that falls back to the striped placeholder on load error. */
function PhotoSlot({ src, alt, h, color, className }: PhotoSlotProps) {
  const [failed, setFailed] = useState(false);
  if (!src || failed) {
    return <ImgPlaceholder label="" color={color} h={h} radius="var(--r-lg)" className={className} />;
  }
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={src}
      alt={alt}
      onError={() => setFailed(true)}
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
      <PhotoSlot src={photos[0]} alt={alt} h={320} color="#cfe0ee" className="gallery-main" />
      <div className="gallery-side">
        <PhotoSlot src={photos[1]} alt={alt} h={152} color="#e4ddd4" />
        <PhotoSlot src={photos[2]} alt={alt} h={152} color="#dde7e1" />
      </div>
    </div>
  );
}
