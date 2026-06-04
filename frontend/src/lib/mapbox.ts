import { useEffect, useState } from "react";

/** Public Mapbox token, inlined at build time (NEXT_PUBLIC_*). */
export const MAPBOX_TOKEN = process.env.NEXT_PUBLIC_MAPBOX_TOKEN ?? "";

export const MAPBOX_STYLE_LIGHT = "mapbox://styles/mapbox/streets-v12";
export const MAPBOX_STYLE_DARK = "mapbox://styles/mapbox/dark-v11";

/** Gaza City — default map centre when a workspace has no coordinates yet. */
export const GAZA_CENTER = { latitude: 31.5, longitude: 34.47 };
export const DEFAULT_ZOOM = 11;

/** Whether a usable Mapbox token is configured (graceful fallback otherwise). */
export function hasMapboxToken(): boolean {
  return MAPBOX_TOKEN.startsWith("pk.");
}

/** Mapbox style URL that follows the site's light/dark theme. */
export function useMapStyle(): string {
  const [dark, setDark] = useState(false);
  useEffect(() => {
    const root = document.documentElement;
    const sync = () => setDark(root.getAttribute("data-theme") === "dark");
    sync();
    const observer = new MutationObserver(sync);
    observer.observe(root, { attributes: true, attributeFilter: ["data-theme"] });
    return () => observer.disconnect();
  }, []);
  return dark ? MAPBOX_STYLE_DARK : MAPBOX_STYLE_LIGHT;
}
