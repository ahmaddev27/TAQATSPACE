"use client";

import { useState } from "react";

export interface GalleryProps {
  photos: string[];
  alt: string;
}

const PLACEHOLDER = "/images/workspaces/cowork-open.jpg";

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
 * Workspace gallery: one large hero image plus a thumbnail strip of EVERY
 * uploaded photo (selecting a thumbnail swaps the hero). This shows all photos
 * rather than capping at the first few; a single placeholder is used only when
 * no photos exist.
 */
export function Gallery({ photos, alt }: GalleryProps) {
  const real = photos.filter(Boolean);
  const list = real.length > 0 ? real : [PLACEHOLDER];
  const [active, setActive] = useState(0);
  const index = Math.min(active, list.length - 1);

  return (
    <div className="gallery-viewer">
      {/* `key` remounts the hero per photo so a prior load-failure doesn't
          stick when switching to a different image. */}
      <PhotoSlot key={list[index]} src={list[index]} alt={alt} h={360} />

      {list.length > 1 && (
        <div className="gallery-thumbs" role="tablist" aria-label={alt}>
          {list.map((src, i) => (
            <button
              key={`${src}-${i}`}
              type="button"
              role="tab"
              aria-selected={i === index}
              aria-label={`${alt} ${i + 1}`}
              className={`gallery-thumb${i === index ? " is-active" : ""}`}
              onClick={() => setActive(i)}
            >
              <PhotoSlot key={src} src={src} alt={`${alt} ${i + 1}`} h={68} />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
