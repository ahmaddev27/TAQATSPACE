import type { ReactNode } from "react";
import { Icon, type IconName } from "./Icon";
import { Sparkline } from "./Sparkline";

export interface StatTileProps {
  icon: IconName;
  label: ReactNode;
  value: ReactNode;
  amber?: boolean;
  delta?: string;
  deltaDir?: "up" | "down";
  foot?: ReactNode;
  spark?: number[];
}

/** Compact KPI tile used across dashboards. Ports the prototype `StatTile`. */
export function StatTile({
  icon,
  label,
  value,
  amber,
  delta,
  deltaDir,
  foot,
  spark,
}: StatTileProps) {
  return (
    <div className="stat-tile">
      <div className="st-head">
        <span className="st-label">{label}</span>
        <span className={`st-ico ${amber ? "amber" : ""}`.trim()}>
          <Icon name={icon} />
        </span>
      </div>
      <div className="st-num tnum">{value}</div>
      <div className="st-foot">
        {delta && (
          <span className={`delta ${deltaDir ?? ""}`.trim()}>
            <Icon name={deltaDir === "down" ? "arrowDown" : "arrowUp"} />
            {delta}
          </span>
        )}
        {foot && <span className="muted-3">{foot}</span>}
      </div>
      {spark && <Sparkline data={spark} />}
    </div>
  );
}
