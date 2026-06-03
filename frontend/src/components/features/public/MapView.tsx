"use client";

import { useEffect } from "react";
import { MapContainer, TileLayer, Marker, useMap } from "react-leaflet";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

export interface MapPoint {
  id: string;
  name: string;
  lat: number;
  lng: number;
  price: number;
}

export interface MapViewProps {
  points: MapPoint[];
  activeId: string | null;
  onSelect: (id: string) => void;
  currency: string;
}

// Gaza City — default centre when no points carry coordinates.
const GAZA_CENTER: [number, number] = [31.5, 34.47];
const DEFAULT_ZOOM = 12;

/** Build a price-label pin styled to match the prototype `.map-pin`. */
function priceIcon(currency: string, price: number, active: boolean): L.DivIcon {
  return L.divIcon({
    className: "map-pin-wrap",
    html: `<span class="map-pin ${active ? "is-active" : ""}"><span class="tnum">${currency}${price}</span></span>`,
    iconSize: [0, 0],
    iconAnchor: [0, 0],
  });
}

/** Pan the map to the active point when it changes from the list side. */
function PanToActive({ points, activeId }: { points: MapPoint[]; activeId: string | null }) {
  const map = useMap();
  useEffect(() => {
    const target = points.find((p) => p.id === activeId);
    if (target) {
      map.panTo([target.lat, target.lng], { animate: true });
    }
  }, [activeId, points, map]);
  return null;
}

export function MapView({ points, activeId, onSelect, currency }: MapViewProps) {
  const center: [number, number] =
    points.length > 0 ? [points[0].lat, points[0].lng] : GAZA_CENTER;

  return (
    <div className="map-canvas">
      {/* Neutralise the prototype `.map-pin` absolute positioning inside leaflet markers. */}
      <style>{`
        .leaflet-marker-icon .map-pin {
          position: static; transform: none; display: inline-flex; align-items: center;
          line-height: 1; white-space: nowrap; pointer-events: auto;
        }
        .leaflet-marker-icon .map-pin.is-active { transform: scale(1.08); }
        .leaflet-container { font-family: inherit; background: var(--surface-2); }
      `}</style>
      <MapContainer
        center={center}
        zoom={DEFAULT_ZOOM}
        scrollWheelZoom={false}
        style={{ height: 520, width: "100%", borderRadius: "var(--r-lg)" }}
      >
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
        {points.map((p) => (
          <Marker
            key={p.id}
            position={[p.lat, p.lng]}
            icon={priceIcon(currency, p.price, p.id === activeId)}
            eventHandlers={{ click: () => onSelect(p.id) }}
          />
        ))}
        <PanToActive points={points} activeId={activeId} />
      </MapContainer>
    </div>
  );
}
