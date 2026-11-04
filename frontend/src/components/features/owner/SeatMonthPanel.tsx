import { useTranslations } from "next-intl";
import type { SeatMonthOccupancy } from "@/lib/types";

/** "Month" tab: how many days each station was held this month. */
export function SeatMonthPanel({ occupancy }: { occupancy: SeatMonthOccupancy }) {
  const t = useTranslations("owner");

  if (occupancy.seats.length === 0) {
    return <p className="muted">{t("seats.stations.monthEmpty")}</p>;
  }

  return (
    <div className="card card-pad stack" style={{ gap: 12 }}>
      <h3 className="h3">{t("seats.stations.monthTitle", { month: occupancy.month })}</h3>
      <table className="table">
        <thead>
          <tr>
            <th>{t("seats.stations.colNumber")}</th>
            <th>{t("seats.stations.colDays")}</th>
            <th>{t("seats.stations.colRate")}</th>
          </tr>
        </thead>
        <tbody>
          {occupancy.seats.map((row) => (
            <tr key={row.seat_id}>
              <td className="tnum">{row.seat_number}</td>
              <td className="tnum">
                {row.occupied_days}/{occupancy.days_in_month}
              </td>
              <td className="tnum">{Math.round(row.occupancy_rate * 100)}%</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
