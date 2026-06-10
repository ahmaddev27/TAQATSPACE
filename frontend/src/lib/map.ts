/**
 * Map config — MapLibre GL + OpenFreeMap (https://openfreemap.org).
 * Free hosted vector tiles: no API key, no account, no credit card.
 *
 * "positron" is a clean, light, minimal basemap (CARTO-style) so the pins stand
 * out — preferred over the busier "liberty" for the marketplace UI.
 */
export const MAP_STYLE = "https://tiles.openfreemap.org/styles/positron";

/** Gaza City — default map centre when a workspace has no coordinates yet. */
export const GAZA_CENTER = { latitude: 31.5, longitude: 34.47 };
export const DEFAULT_ZOOM = 11;
