"use client";

import { MapContainer, TileLayer, Marker } from "react-leaflet";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

export interface DetailMapProps {
  lat: number;
  lng: number;
  label: string;
}

const PIN = L.divIcon({
  className: "detail-pin-wrap",
  html: '<span class="detail-pin"></span>',
  iconSize: [18, 18],
  iconAnchor: [9, 9],
});

/** Non-interactive locator map for the workspace detail page. */
export function DetailMap({ lat, lng, label }: DetailMapProps) {
  return (
    <div className="map-canvas" aria-label={label}>
      <style>{`
        .detail-pin { display:block; width:16px; height:16px; border-radius:999px;
          background: var(--primary); border: 3px solid #fff; box-shadow: var(--sh-sm); }
        .leaflet-container { font-family: inherit; background: var(--surface-2); }
      `}</style>
      <MapContainer
        center={[lat, lng]}
        zoom={15}
        scrollWheelZoom={false}
        dragging={false}
        doubleClickZoom={false}
        zoomControl={false}
        attributionControl={false}
        style={{ height: 240, width: "100%", borderRadius: "var(--r-lg)" }}
      >
        <TileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
        <Marker position={[lat, lng]} icon={PIN} />
      </MapContainer>
    </div>
  );
}
