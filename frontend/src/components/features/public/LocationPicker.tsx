"use client";

import "maplibre-gl/dist/maplibre-gl.css";
import Map, {
  Marker,
  NavigationControl,
  type MapLayerMouseEvent,
  type MarkerDragEvent,
} from "react-map-gl/maplibre";
import { GAZA_CENTER, DEFAULT_ZOOM, MAP_STYLE } from "@/lib/map";

export interface LocationPickerProps {
  lat: number;
  lng: number;
  onChange: (lat: number, lng: number) => void;
  label: string;
  hint: string;
}

/**
 * Interactive map picker for the workspace-register location step: the owner
 * clicks the map or drags the pin to set the exact lat/lng of their space.
 */
export function LocationPicker({ lat, lng, onChange, label, hint }: LocationPickerProps) {
  const hasPoint = Number.isFinite(lat) && Number.isFinite(lng) && (lat !== 0 || lng !== 0);
  const latitude = hasPoint ? lat : GAZA_CENTER.latitude;
  const longitude = hasPoint ? lng : GAZA_CENTER.longitude;

  const move = (la: number, ln: number) =>
    onChange(Number(la.toFixed(6)), Number(ln.toFixed(6)));

  return (
    <div className="stack" style={{ gap: 8 }}>
      <div className="map-canvas" aria-label={label}>
        <Map
          initialViewState={{ latitude, longitude, zoom: DEFAULT_ZOOM }}
          mapStyle={MAP_STYLE}
          style={{ height: 320, width: "100%", borderRadius: "var(--r-lg)" }}
          onClick={(e: MapLayerMouseEvent) => move(e.lngLat.lat, e.lngLat.lng)}
        >
          <NavigationControl position="top-right" showCompass={false} />
          <Marker
            latitude={latitude}
            longitude={longitude}
            anchor="bottom"
            draggable
            color="#1F82C7"
            onDragEnd={(e: MarkerDragEvent) => move(e.lngLat.lat, e.lngLat.lng)}
          />
        </Map>
      </div>
      <p className="muted" style={{ fontSize: "var(--fs-xs)" }}>
        {hint} —{" "}
        <span className="tnum ltr">
          {latitude.toFixed(5)}, {longitude.toFixed(5)}
        </span>
      </p>
    </div>
  );
}
