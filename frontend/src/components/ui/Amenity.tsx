import type { ReactNode } from "react";
import { Icon, type IconName } from "./Icon";

/** Maps backend amenity codes to a representative icon. */
export const AMENITY_ICONS: Record<string, IconName> = {
  wifi: "wifi",
  printer: "printer",
  meeting_room: "users",
  parking: "parking",
  coffee: "coffee",
  kitchen: "coffee",
  snow: "snow",
  ac: "snow",
};

export interface AmenityProps {
  /** Backend amenity code; used to pick the icon when `icon` is omitted. */
  code?: string;
  icon?: IconName;
  label: ReactNode;
}

/** A single amenity chip (icon + label). */
export function Amenity({ code, icon, label }: AmenityProps) {
  const resolved = icon ?? (code ? AMENITY_ICONS[code] : undefined) ?? "check";
  return (
    <span className="amenity">
      <Icon name={resolved} size={16} />
      {label}
    </span>
  );
}
