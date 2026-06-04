"use client";

import "mapbox-gl/dist/mapbox-gl.css";
import Map, { Marker } from "react-map-gl";
import { MAPBOX_TOKEN, hasMapboxToken, useMapStyle } from "@/lib/mapbox";

export interface DetailMapProps {
  lat: number;
  lng: number;
  label: string;
}

/** Non-interactive locator map for the workspace detail page. */
export function DetailMap({ lat, lng, label }: DetailMapProps) {
  const mapStyle = useMapStyle();

  if (!hasMapboxToken()) {
    return (
      <div className="map-canvas" aria-label={label}>
        <div className="map-fallback" style={{ height: 240 }} />
      </div>
    );
  }

  return (
    <div className="map-canvas" aria-label={label}>
      <Map
        mapboxAccessToken={MAPBOX_TOKEN}
        initialViewState={{ latitude: lat, longitude: lng, zoom: 14 }}
        mapStyle={mapStyle}
        interactive={false}
        style={{ height: 240, width: "100%", borderRadius: "var(--r-lg)" }}
      >
        <Marker latitude={lat} longitude={lng} color="#1F82C7" anchor="bottom" />
      </Map>
    </div>
  );
}
