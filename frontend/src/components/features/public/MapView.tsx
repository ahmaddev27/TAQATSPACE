"use client";

import "mapbox-gl/dist/mapbox-gl.css";
import { useEffect, useRef } from "react";
import Map, { Marker, NavigationControl, type MapRef } from "react-map-gl";
import { MAPBOX_TOKEN, GAZA_CENTER, hasMapboxToken, useMapStyle } from "@/lib/mapbox";

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

/** Explore map: a price pin per workspace, panning to the active card. */
export function MapView({ points, activeId, onSelect, currency }: MapViewProps) {
  const mapStyle = useMapStyle();
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

  if (!hasMapboxToken()) {
    return (
      <div className="map-canvas">
        <div className="map-fallback" style={{ height: 520 }} />
      </div>
    );
  }

  return (
    <div className="map-canvas">
      <Map
        ref={mapRef}
        mapboxAccessToken={MAPBOX_TOKEN}
        initialViewState={{ ...center, zoom: 12 }}
        mapStyle={mapStyle}
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
