import type { IconName } from "@/components/ui/Icon";
import type { PublicDict } from "./i18n";

/** Backend numeric fields arrive as strings (e.g. "25.00"); coerce safely. */
export function toNumber(value: unknown): number {
  const n = typeof value === "number" ? value : Number(value);
  return Number.isFinite(n) ? n : 0;
}

/** Origin that serves Laravel `storage/` assets (strip the trailing `/api`). */
const STORAGE_ORIGIN = (
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api"
).replace(/\/api\/?$/, "");

/** Resolve a stored photo path to an absolute URL (already-absolute passes through). */
export function photoUrl(path: string): string {
  if (!path) return "";
  if (/^https?:\/\//.test(path)) return path;
  const clean = path.replace(/^\/+/, "");
  return `${STORAGE_ORIGIN}/storage/${clean}`;
}

/** Bundled real coworking cover photos (shown until a workspace uploads its own). */
const COVER_PHOTOS = [
  "/images/workspaces/cowork-open.jpg",
  "/images/workspaces/cowork-lounge.jpg",
  "/images/workspaces/cowork-meeting.jpg",
  "/images/workspaces/cowork-team.jpg",
  "/images/workspaces/cowork-space.jpg",
];

/** Pick a stable cover photo from any id string (so cards vary, not repeat). */
export function placeholderCover(seed: string): string {
  let hash = 0;
  for (let i = 0; i < seed.length; i += 1) {
    hash = (hash * 31 + seed.charCodeAt(i)) >>> 0;
  }
  return COVER_PHOTOS[hash % COVER_PHOTOS.length];
}

/** Cover image for a workspace: first real photo if any, else a placeholder. */
export function coverImage(photos: string[] | undefined, seed: string): string {
  const first = photos?.find(Boolean);
  return first ? photoUrl(first) : placeholderCover(seed);
}

/** Amenity code -> representative icon (mirrors the prototype amenity map). */
export const AMENITY_ICON: Record<string, IconName> = {
  wifi: "wifi",
  printer: "printer",
  meeting_room: "users",
  parking: "parking",
  coffee: "coffee",
  kitchen: "coffee",
  snow: "snow",
  ac: "snow",
};

const AMENITY_LABELS: Record<string, { ar: string; en: string }> = {
  wifi: { ar: "إنترنت سريع", en: "Fast Wi-Fi" },
  printer: { ar: "طابعة", en: "Printer" },
  meeting_room: { ar: "غرفة اجتماعات", en: "Meeting room" },
  parking: { ar: "موقف سيارات", en: "Parking" },
  coffee: { ar: "قهوة مجانية", en: "Free coffee" },
  kitchen: { ar: "مطبخ", en: "Kitchen" },
  snow: { ar: "تكييف", en: "Air conditioning" },
  ac: { ar: "تكييف", en: "Air conditioning" },
};

export function amenityLabel(code: string, locale: string): string {
  const entry = AMENITY_LABELS[code];
  if (!entry) return code;
  return locale === "en" ? entry.en : entry.ar;
}

/** Full amenity catalogue (for the detail page on/off grid). */
export function amenityCatalogue(): string[] {
  return Object.keys(AMENITY_LABELS).filter((c) => c !== "ac");
}

/** Compute an occupancy percentage from a seats summary, guarding divide-by-zero. */
export function occupancyPct(occupied: number, total: number): number {
  if (total <= 0) return 0;
  return Math.round((occupied / total) * 100);
}

/** Localised seat-type label using the public dictionary. */
export function seatTypeLabel(
  type: string,
  dict: PublicDict["detail"],
): string {
  switch (type) {
    case "fixed":
      return dict.seatFixed;
    case "private_office":
      return dict.seatPrivate;
    default:
      return dict.seatOpen;
  }
}
