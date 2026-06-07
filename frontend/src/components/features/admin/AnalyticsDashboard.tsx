"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import type { AdminAnalytics } from "@/lib/api/admin";

/* -------------------------------------------------------------------------- */
/*  Brand palette (mirrors styles/tokens.css and ReportsDashboard)             */
/* -------------------------------------------------------------------------- */

const COLOR = {
  primary: "#1F82C7",
  amber: "#F6A91B",
  muted: "#94A3B8",
} as const;

/** Stable colour per gender bucket so the same bucket keeps its colour. */
const GENDER_COLOR: Record<string, string> = {
  male: COLOR.primary,
  female: COLOR.amber,
  unspecified: COLOR.muted,
};

const AXIS = {
  stroke: "var(--text-3)",
  fontSize: 11,
  fontFamily: "var(--font-num)",
} as const;

/** Shared tooltip surface styling. */
const tooltipStyle = {
  background: "var(--surface)",
  border: "1px solid var(--border)",
  borderRadius: 8,
  fontSize: "var(--fs-sm)",
  boxShadow: "var(--sh-sm)",
} as const;

interface GenderDatum {
  name: string;
  value: number;
  color: string;
}

export interface AnalyticsDashboardProps {
  data: AdminAnalytics;
}

/**
 * Demographic + geographic analytics for the super-admin: a per-city bar chart
 * (workspaces vs. active members) and a gender-distribution donut. Reuses the
 * same recharts primitives + brand palette as {@link ReportsDashboard}.
 */
export function AnalyticsDashboard({ data }: AnalyticsDashboardProps) {
  const t = useTranslations("admin.analytics");
  const tg = useTranslations("common.gender");

  const cityData = useMemo(
    () =>
      data.by_city.map((row) => ({
        label: row.label,
        workspaces: row.workspaces,
        members: row.active_members,
      })),
    [data.by_city],
  );

  const genderData = useMemo<GenderDatum[]>(
    () =>
      data.by_gender
        .map((row) => ({
          name: tg(row.label),
          value: row.value,
          color: GENDER_COLOR[row.label] ?? COLOR.primary,
        }))
        .filter((d) => d.value > 0),
    [data.by_gender, tg],
  );

  return (
    <div className="stack" style={{ gap: 24, marginTop: 8 }}>
      <section className="card card-pad stack" style={{ gap: 16 }}>
        <div>
          <h3 className="h3">{t("byCityTitle")}</h3>
          <p className="muted-3" style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}>
            {t("byCitySubtitle")}
          </p>
        </div>
        {cityData.length === 0 ? (
          <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("noData")}
          </p>
        ) : (
          <div style={{ width: "100%", height: 300 }}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={cityData} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
                <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="label" tick={AXIS} tickLine={false} axisLine={{ stroke: "var(--border)" }} />
                <YAxis tick={AXIS} tickLine={false} axisLine={false} width={36} allowDecimals={false} />
                <Tooltip
                  formatter={(value, name) => [
                    value,
                    name === "workspaces" ? t("workspaces") : t("activeMembers"),
                  ]}
                  contentStyle={tooltipStyle}
                  labelStyle={{ color: "var(--text-2)" }}
                  cursor={{ fill: "var(--surface-sunk)" }}
                />
                <Legend
                  formatter={(value) =>
                    value === "workspaces" ? t("workspaces") : t("activeMembers")
                  }
                  wrapperStyle={{ fontSize: "var(--fs-sm)" }}
                />
                <Bar dataKey="workspaces" fill={COLOR.primary} radius={[4, 4, 0, 0]} maxBarSize={36} />
                <Bar dataKey="members" fill={COLOR.amber} radius={[4, 4, 0, 0]} maxBarSize={36} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        )}
      </section>

      <section className="card card-pad stack" style={{ gap: 12 }}>
        <div>
          <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
            {t("byGenderTitle")}
          </h3>
          <p className="muted-3" style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}>
            {t("byGenderSubtitle")}
          </p>
        </div>
        <GenderDonut data={genderData} emptyLabel={t("noData")} />
      </section>
    </div>
  );
}

/** A donut chart for the gender breakdown, with an empty fallback. */
function GenderDonut({
  data,
  emptyLabel,
}: {
  data: GenderDatum[];
  emptyLabel: string;
}) {
  if (data.length === 0) {
    return (
      <div
        style={{
          height: 220,
          display: "grid",
          placeItems: "center",
          color: "var(--text-3)",
          fontSize: "var(--fs-sm)",
        }}
      >
        {emptyLabel}
      </div>
    );
  }

  return (
    <div style={{ width: "100%", height: 240 }}>
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={data}
            dataKey="value"
            nameKey="name"
            innerRadius={60}
            outerRadius={90}
            paddingAngle={2}
            stroke="var(--surface)"
            strokeWidth={2}
          >
            {data.map((d) => (
              <Cell key={d.name} fill={d.color} />
            ))}
          </Pie>
          <Tooltip contentStyle={tooltipStyle} />
          <Legend
            verticalAlign="middle"
            align="right"
            layout="vertical"
            iconType="circle"
            wrapperStyle={{ fontSize: "var(--fs-sm)" }}
          />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
}
