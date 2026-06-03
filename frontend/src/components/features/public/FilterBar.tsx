"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter, usePathname } from "@/i18n/navigation";
import { useSearchParams } from "next/navigation";
import { Icon } from "@/components/ui/Icon";
import { Select } from "@/components/ui/Select";
import { Checkbox } from "@/components/ui/Checkbox";
import { Button } from "@/components/ui/Button";
import type { PublicDict } from "./i18n";
import { interpolate } from "./i18n";
import { amenityLabel } from "./helpers";

const AMENITY_CODES = ["wifi", "coffee", "parking", "snow", "printer", "meeting_room"] as const;
const SEAT_TYPES = ["fixed", "flexible", "private_office"] as const;
const RATINGS = [3, 4, 4.5] as const;

export interface FilterBarProps {
  dict: PublicDict;
  cities: string[];
  locale: string;
  seatType?: string;
}

/** Sticky discovery filter bar; all state lives in the URL query string. */
export function FilterBar({ dict, cities, locale, seatType }: FilterBarProps) {
  const e = dict.explore;
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const [search, setSearch] = useState(params.get("search") ?? "");
  const [showAmenities, setShowAmenities] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const selectedAmenities = params.getAll("amenities");

  const pushParams = useCallback(
    (mutate: (next: URLSearchParams) => void) => {
      const next = new URLSearchParams(Array.from(params.entries()));
      mutate(next);
      const qs = next.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
    },
    [params, pathname, router],
  );

  const setSingle = useCallback(
    (key: string, value: string) => {
      pushParams((next) => {
        if (value) next.set(key, value);
        else next.delete(key);
      });
    },
    [pushParams],
  );

  function toggleAmenity(code: string, checked: boolean) {
    pushParams((next) => {
      const current = next.getAll("amenities").filter((a) => a !== code);
      next.delete("amenities");
      const updated = checked ? [...current, code] : current;
      updated.forEach((a) => next.append("amenities", a));
    });
  }

  // Debounce the free-text search before writing it to the URL.
  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      const current = params.get("search") ?? "";
      if (current !== search) setSingle("search", search);
    }, 350);
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  function clearAll() {
    setSearch("");
    router.replace(pathname, { scroll: false });
  }

  const hasFilters = Array.from(params.keys()).some((k) => k !== "sort");

  function seatLabel(s: string): string {
    if (s === "fixed") return dict.detail.seatFixed;
    if (s === "private_office") return dict.detail.seatPrivate;
    return dict.detail.seatOpen;
  }

  return (
    <div className="stack" style={{ gap: 0 }}>
      <div className="filter-bar">
        <div className="input-icon grow" style={{ maxWidth: 300 }}>
          <Icon name="search" />
          <input
            className="input"
            placeholder={e.searchPlaceholder}
            value={search}
            onChange={(ev) => setSearch(ev.target.value)}
          />
        </div>

        <Select value={params.get("city") ?? ""} onChange={(ev) => setSingle("city", ev.target.value)}>
          <option value="">{e.allCities}</option>
          {cities.map((city) => (
            <option key={city} value={city}>
              {city}
            </option>
          ))}
        </Select>

        <div className="input-icon" style={{ width: 96 }}>
          <input
            className="input"
            type="number"
            inputMode="numeric"
            min={0}
            placeholder={e.minPrice}
            value={params.get("min_price") ?? ""}
            onChange={(ev) => setSingle("min_price", ev.target.value)}
          />
        </div>
        <div className="input-icon" style={{ width: 96 }}>
          <input
            className="input"
            type="number"
            inputMode="numeric"
            min={0}
            placeholder={e.maxPrice}
            value={params.get("max_price") ?? ""}
            onChange={(ev) => setSingle("max_price", ev.target.value)}
          />
        </div>

        <Select value={seatType ?? ""} onChange={(ev) => setSingle("seat_type", ev.target.value)}>
          <option value="">{e.anySeatType}</option>
          {SEAT_TYPES.map((s) => (
            <option key={s} value={s}>
              {seatLabel(s)}
            </option>
          ))}
        </Select>

        <Select
          value={params.get("min_rating") ?? ""}
          onChange={(ev) => setSingle("min_rating", ev.target.value)}
        >
          <option value="">{e.anyRating}</option>
          {RATINGS.map((r) => (
            <option key={r} value={r}>
              {interpolate(e.ratingPlus, { value: r })}
            </option>
          ))}
        </Select>

        <button
          type="button"
          className="btn btn-secondary btn-sm"
          onClick={() => setShowAmenities((v) => !v)}
          aria-expanded={showAmenities}
        >
          <Icon name="filter" size={16} />
          {e.amenities}
          {selectedAmenities.length > 0 ? ` (${selectedAmenities.length})` : ""}
        </button>

        <div className="grow" />

        <div className="row" style={{ gap: 8 }}>
          <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
            {e.sort}:
          </span>
          <Select
            value={params.get("sort") ?? "rating"}
            onChange={(ev) => setSingle("sort", ev.target.value)}
            style={{ width: 150 }}
          >
            <option value="rating">{e.sortRating}</option>
            <option value="price">{e.sortPriceAsc}</option>
            <option value="-price">{e.sortPriceDesc}</option>
          </Select>
        </div>

        {hasFilters && (
          <Button variant="ghost" size="sm" icon="x" onClick={clearAll}>
            {e.clear}
          </Button>
        )}
      </div>

      {showAmenities && (
        <div
          className="row wrap"
          style={{
            gap: 16,
            paddingBottom: 18,
            borderTop: "1px solid var(--border)",
            paddingTop: 16,
          }}
        >
          {AMENITY_CODES.map((code) => (
            <Checkbox
              key={code}
              checked={selectedAmenities.includes(code)}
              onChange={(ev) => toggleAmenity(code, ev.target.checked)}
            >
              {amenityLabel(code, locale)}
            </Checkbox>
          ))}
        </div>
      )}
    </div>
  );
}
