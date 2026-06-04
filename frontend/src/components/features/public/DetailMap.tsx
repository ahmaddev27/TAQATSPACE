"use client";

import "maplibre-gl/dist/maplibre-gl.css";
import Map, { Marker } from "react-map-gl/maplibre";
import { MAP_STYLE } from "@/lib/map";

export interface DetailMapProps {
  lat: number;
  lng: number;
  label: string;
}

/** Non-interactive locator map for the workspace detail page. */
export function DetailMap({ lat, lng, label }: DetailMapProps) {
  return (
    <div className="map-canvas" aria-label={label}>
      <Map
        initialViewState={{ latitude: lat, longitude: lng, zoom: 14 }}
        mapStyle={MAP_STYLE}
        interactive={false}
        style={{ height: 240, width: "100%", borderRadius: "var(--r-lg)" }}
      >
        <Marker latitude={lat} longitude={lng} color="#1F82C7" anchor="bottom" />
      </Map>
    </div>
  );
}
