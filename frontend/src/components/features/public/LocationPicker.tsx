"use client";

import "mapbox-gl/dist/mapbox-gl.css";
import Map, {
  Marker,
  NavigationControl,
  type MapLayerMouseEvent,
  type MarkerDragEvent,
} from "react-map-gl";
import { Icon } from "@/components/ui/Icon";
import {
  MAPBOX_TOKEN,
  GAZA_CENTER,
  DEFAULT_ZOOM,
  hasMapboxToken,
  useMapStyle,
} from "@/lib/mapbox";

export interface LocationPickerProps {
  lat: number;
  lng: number;
  onChange: (lat: number, lng: number) => void;
  label: string;
  hint: string;
}

/**
 * Interactive Mapbox picker for the workspace-register location step: the owner
 * clicks the map or drags the pin to set the exact lat/lng of their space.
 */
export function LocationPicker({ lat, lng, onChange, label, hint }: LocationPickerProps) {
  const mapStyle = useMapStyle();

  const hasPoint = Number.isFinite(lat) && Number.isFinite(lng) && (lat !== 0 || lng !== 0);
  const latitude = hasPoint ? lat : GAZA_CENTER.latitude;
  const longitude = hasPoint ? lng : GAZA_CENTER.longitude;

  // No token yet → keep the form usable with a static stub (location defaults to Gaza).
  if (!hasMapboxToken()) {
    return (
      <div className="map-stub" role="img" aria-label={label}>
        <Icon name="pin" size={28} />
        <span style={{ fontWeight: 600, color: "var(--text-2)" }}>{label}</span>
        <span className="map-stub-note">{hint}</span>
      </div>
    );
  }

  const move = (la: number, ln: number) =>
    onChange(Number(la.toFixed(6)), Number(ln.toFixed(6)));

  return (
    <div className="stack" style={{ gap: 8 }}>
      <div className="map-canvas">
        <Map
          mapboxAccessToken={MAPBOX_TOKEN}
          initialViewState={{ latitude, longitude, zoom: DEFAULT_ZOOM }}
          mapStyle={mapStyle}
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
