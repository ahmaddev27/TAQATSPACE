import { Icon } from "@/components/ui/Icon";
import type { PublicDict } from "./i18n";
import { AMENITY_ICON, amenityCatalogue, amenityLabel } from "./helpers";

export interface AmenityGridProps {
  amenities: string[];
  locale: string;
  dict: PublicDict;
}

/** Full amenity catalogue with on/off check marks (ported from prototype). */
export function AmenityGrid({ amenities, locale, dict }: AmenityGridProps) {
  const owned = new Set(amenities);
  const catalogue = amenityCatalogue();
  // Owner-typed amenities outside the preset catalogue (the "ac" alias aside),
  // shown as extra "on" items so custom entries aren't silently dropped.
  const catalogueSet = new Set([...catalogue, "ac"]);
  const custom = amenities.filter((code) => !catalogueSet.has(code));
  return (
    <div>
      <h3 className="h3" style={{ marginBottom: 14 }}>
        {dict.detail.amenities}
      </h3>
      <div className="amen-grid">
        {catalogue.map((code) => {
          const has = owned.has(code);
          return (
            <div key={code} className={`amen-item ${has ? "" : "is-off"}`}>
              <Icon name={AMENITY_ICON[code] ?? "check"} size={18} />
              {amenityLabel(code, locale)}
              {has ? (
                <Icon name="check" size={15} className="amen-yes" />
              ) : (
                <Icon name="x" size={14} className="amen-no" />
              )}
            </div>
          );
        })}
        {custom.map((code) => (
          <div key={code} className="amen-item">
            <Icon name="check" size={18} />
            {amenityLabel(code, locale)}
            <Icon name="check" size={15} className="amen-yes" />
          </div>
        ))}
      </div>
    </div>
  );
}
