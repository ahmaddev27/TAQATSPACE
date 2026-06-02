import { Icon } from "@/components/ui/Icon";

export interface TileLogoProps {
  size?: number;
}

/** Tile logotype motif: T A [bulb] A T. */
export function TileLogo({ size = 54 }: TileLogoProps) {
  const tiles = ["T", "A", "Q", "A", "T"];
  return (
    <div className="tiles" style={{ gap: size * 0.14, fontSize: size }}>
      {tiles.map((c, i) =>
        i === 2 ? (
          <span key={i} className="tile-glyph is-bulb" aria-label="Q">
            <Icon name="bulb" />
          </span>
        ) : (
          <span key={i} className="tile-glyph">
            {c}
          </span>
        ),
      )}
    </div>
  );
}
