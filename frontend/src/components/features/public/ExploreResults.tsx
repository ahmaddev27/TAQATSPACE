"use client";

import { useMemo, useState, useTransition } from "react";
import dynamic from "next/dynamic";
import { useRouter } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { Rating } from "@/components/ui/Rating";
import { Amenity } from "@/components/ui/Amenity";
import { ImgPlaceholder } from "@/components/ui/ImgPlaceholder";
import { CoverImage } from "@/components/ui/CoverImage";
import type { Workspace } from "@/lib/types";
import type { WorkspaceFilters } from "@/lib/api/workspaces";
import { fetchMoreWorkspaces } from "@/lib/actions/workspaces";
import type { PublicDict } from "./i18n";
import { amenityLabel, coverImage, placeholderCover, occupancyPct, toNumber } from "./helpers";

// Leaflet touches `window`; load it only on the client.
const MapView = dynamic(() => import("./MapView").then((m) => m.MapView), {
  ssr: false,
  loading: () => (
    <ImgPlaceholder label="" color="#dde6ec" h={520} radius="var(--r-lg)" />
  ),
});

export interface ExploreResultsProps {
  workspaces: Workspace[];
  initialHasMore: boolean;
  filters: WorkspaceFilters;
  dict: PublicDict;
  locale: string;
}

export function ExploreResults({
  workspaces,
  initialHasMore,
  filters,
  dict,
  locale,
}: ExploreResultsProps) {
  const e = dict.explore;
  const c = dict.common;
  const router = useRouter();
  const [activeId, setActiveId] = useState<string | null>(workspaces[0]?.id ?? null);

  // Appended pages from "load more"; starts with the server-rendered first page.
  const [items, setItems] = useState(workspaces);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(initialHasMore);
  const [loadingMore, startLoad] = useTransition();

  const loadMore = () => {
    startLoad(async () => {
      const next = await fetchMoreWorkspaces(filters, page + 1);
      setItems((prev) => [...prev, ...next.workspaces]);
      setPage((p) => p + 1);
      setHasMore(next.hasMore);
    });
  };

  // Only workspaces with coordinates can render a pin.
  const mapPoints = useMemo(
    () =>
      items
        .filter((w) => w.latitude != null && w.longitude != null)
        .map((w) => ({
          id: w.id,
          name: w.name,
          lat: toNumber(w.latitude),
          lng: toNumber(w.longitude),
          price: toNumber(w.price_per_month),
        })),
    [items],
  );

  if (items.length === 0) {
    return (
      <div className="container" style={{ padding: "64px 0 96px" }}>
        <div className="card card-pad" style={{ textAlign: "center", maxWidth: 440, margin: "0 auto" }}>
          <div className="confirm-art" style={{ background: "var(--surface-2)", color: "var(--text-3)" }}>
            <Icon name="search" size={30} />
          </div>
          <h3 className="h3" style={{ marginTop: 18 }}>
            {e.empty}
          </h3>
          <p className="muted" style={{ marginTop: 8 }}>
            {e.emptyHint}
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="container explore-split">
      <div className="explore-list">
        {items.map((w) => {
          const price = toNumber(w.price_per_month);
          const rating = toNumber(w.avg_rating);
          const amenities = w.amenities ?? [];
          const occ = occupancyPct(w.seats_summary?.occupied ?? 0, w.total_seats);
          return (
            <div
              key={w.id}
              className={`card card-hover explore-row ${activeId === w.id ? "is-active" : ""}`}
              onMouseEnter={() => setActiveId(w.id)}
              onClick={() => router.push(`/workspaces/${w.id}`)}
              role="button"
              tabIndex={0}
              onKeyDown={(ev) => {
                if (ev.key === "Enter") router.push(`/workspaces/${w.id}`);
              }}
            >
              <div className="explore-row-img">
                <CoverImage
                  src={coverImage(w.photos, w.id)}
                  fallbackSrc={placeholderCover(w.id)}
                  alt={w.name}
                  h="100%"
                  radius="0"
                />
              </div>
              <div className="grow stack" style={{ gap: 6 }}>
                <div className="between">
                  <h3 className="h3" style={{ fontSize: "var(--fs-lg)" }}>
                    {w.name}
                  </h3>
                  {rating > 0 && <Rating value={rating} />}
                </div>
                <div className="row muted" style={{ gap: 6, fontSize: "var(--fs-sm)" }}>
                  <Icon name="pin" size={15} />
                  {w.city}
                  {w.address ? ` · ${w.address}` : ""}
                </div>
                <div className="ws-amen">
                  {amenities.map((a) => (
                    <Amenity key={a} code={a} label={amenityLabel(a, locale)} />
                  ))}
                </div>
                <div className="between" style={{ marginTop: 2 }}>
                  <div>
                    <span className="ws-price tnum">
                      {c.currency}
                      {price}
                    </span>
                    <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
                      {c.perMonth}
                    </span>
                  </div>
                  {w.seats_summary && (
                    <span className="badge badge-info">
                      {occ}% {c.occupancy}
                    </span>
                  )}
                </div>
              </div>
            </div>
          );
        })}

        {hasMore && (
          <button
            type="button"
            className="btn btn-secondary"
            style={{ width: "100%", marginTop: 4 }}
            onClick={loadMore}
            disabled={loadingMore}
          >
            {loadingMore ? "…" : e.loadMore}
          </button>
        )}
      </div>

      <div className="explore-map">
        <MapView
          points={mapPoints}
          activeId={activeId}
          onSelect={setActiveId}
          currency={c.currency}
        />
      </div>
    </div>
  );
}
