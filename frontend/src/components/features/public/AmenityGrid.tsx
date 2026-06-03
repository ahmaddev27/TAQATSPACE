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
  return (
    <div>
      <h3 className="h3" style={{ marginBottom: 14 }}>
        {dict.detail.amenities}
      </h3>
      <div className="amen-grid">
        {amenityCatalogue().map((code) => {
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
      </div>
    </div>
  );
}
