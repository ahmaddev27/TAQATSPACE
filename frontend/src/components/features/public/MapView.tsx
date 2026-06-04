"use client";

import "maplibre-gl/dist/maplibre-gl.css";
import { useEffect, useRef } from "react";
import Map, { Marker, NavigationControl, type MapRef } from "react-map-gl/maplibre";
import { GAZA_CENTER, MAP_STYLE } from "@/lib/map";

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

/** Explore map: a price pin per workspace, flying to the active card. */
export function MapView({ points, activeId, onSelect, currency }: MapViewProps) {
  const mapRef = useRef<MapRef>(null);
  const center =
    points.length > 0
      ? { latitude: points[0].lat, longitude: points[0].lng }
      : GAZA_CENTER;

  useEffect(() => {
    const target = points.find((p) => p.id === activeId);
    if (target) {
      mapRef.current?.flyTo({ center: [target.lng, target.lat], duration: 600 });
    }
  }, [activeId, points]);

  return (
    <div className="map-canvas">
      <Map
        ref={mapRef}
        initialViewState={{ ...center, zoom: 12 }}
        mapStyle={MAP_STYLE}
        style={{ height: 520, width: "100%", borderRadius: "var(--r-lg)" }}
        scrollZoom={false}
      >
        <NavigationControl position="top-right" showCompass={false} />
        {points.map((p) => (
          <Marker key={p.id} latitude={p.lat} longitude={p.lng} anchor="center">
            <button
              type="button"
              className={`map-pin ${p.id === activeId ? "is-active" : ""}`}
              onClick={(e) => {
                e.stopPropagation();
                onSelect(p.id);
              }}
            >
              <span className="tnum">
                {currency}
                {p.price}
              </span>
            </button>
          </Marker>
        ))}
      </Map>
    </div>
  );
}
