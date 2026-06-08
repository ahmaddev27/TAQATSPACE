"use client";

import "maplibre-gl/dist/maplibre-gl.css";
import Map, { Marker, NavigationControl } from "react-map-gl/maplibre";
import { MAP_STYLE } from "@/lib/map";

export interface DetailMapProps {
  lat: number;
  lng: number;
  label: string;
}

/**
 * Interactive locator map for the workspace detail page: visitors can zoom
 * (scroll, the +/- control, double-click or pinch) and pan to explore the area
 * around the workspace. `dragRotate`/compass are off to keep the map north-up.
 */
export function DetailMap({ lat, lng, label }: DetailMapProps) {
  return (
    <div className="map-canvas" aria-label={label}>
      <Map
        initialViewState={{ latitude: lat, longitude: lng, zoom: 14 }}
        mapStyle={MAP_STYLE}
        dragRotate={false}
        touchPitch={false}
        style={{ height: 240, width: "100%", borderRadius: "var(--r-lg)" }}
      >
        <NavigationControl position="top-right" showCompass={false} />
        <Marker latitude={lat} longitude={lng} color="#1F82C7" anchor="bottom" />
      </Map>
    </div>
  );
}
