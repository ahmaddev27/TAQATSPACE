import { Link } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { Rating } from "@/components/ui/Rating";
import { Amenity } from "@/components/ui/Amenity";
import type { Workspace } from "@/lib/types";
import type { PublicDict } from "./i18n";
import { amenityLabel, coverImage, occupancyPct, toNumber } from "./helpers";

export interface WorkspaceCardProps {
  workspace: Workspace;
  dict: PublicDict;
  locale: string;
}

/** Featured / grid workspace card (ported from prototype `WorkspaceCard`). */
export function WorkspaceCard({ workspace, dict, locale }: WorkspaceCardProps) {
  const c = dict.common;
  const price = toNumber(workspace.price_per_month);
  const rating = toNumber(workspace.avg_rating);
  const occupied = workspace.seats_summary?.occupied ?? 0;
  const occ = occupancyPct(occupied, workspace.total_seats);
  const amenities = workspace.amenities ?? [];

  return (
    <Link href={`/workspaces/${workspace.id}`} className="card card-hover ws-card">
      <div className="ws-card-img">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={coverImage(workspace.photos, workspace.id)}
          alt={workspace.name}
          loading="lazy"
          style={{ width: "100%", height: 158, objectFit: "cover", display: "block" }}
        />
      </div>
      <div className="ws-card-body">
        <div className="between">
          <h3 className="h3" style={{ fontSize: "var(--fs-lg)" }}>
            {workspace.name}
          </h3>
          {rating > 0 && <Rating value={rating} />}
        </div>
        <div className="row muted" style={{ gap: 6, fontSize: "var(--fs-sm)" }}>
          <Icon name="pin" size={15} />
          {workspace.city}
          {workspace.address ? ` · ${workspace.address}` : ""}
        </div>
        <div className="ws-amen">
          {amenities.slice(0, 3).map((a) => (
            <Amenity key={a} code={a} label={amenityLabel(a, locale)} />
          ))}
          {amenities.length > 3 && (
            <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
              +{amenities.length - 3}
            </span>
          )}
        </div>
        <div className="divider" style={{ margin: "2px 0" }} />
        <div className="between">
          <div>
            <span className="ws-price tnum">
              {c.currency}
              {price}
            </span>
            <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
              {c.perMonth}
            </span>
          </div>
          {workspace.seats_summary && (
            <span className="badge badge-info">
              <span className="dot" />
              {occ}% {c.occupancy}
            </span>
          )}
        </div>
      </div>
    </Link>
  );
}
