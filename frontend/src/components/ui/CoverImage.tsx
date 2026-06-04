export interface CoverImageProps {
  src: string;
  alt?: string;
  h: number | string;
  radius?: string;
  className?: string;
}

/**
 * A cover `<img>` that fills its slot (object-fit: cover). Used for real photos
 * and for the bundled on-brand placeholders until real photos are uploaded.
 */
export function CoverImage({
  src,
  alt = "",
  h,
  radius = "var(--r-lg)",
  className = "",
}: CoverImageProps) {
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={src}
      alt={alt}
      loading="lazy"
      className={className}
      style={{
        width: "100%",
        height: h,
        objectFit: "cover",
        borderRadius: radius,
        display: "block",
      }}
    />
  );
}
