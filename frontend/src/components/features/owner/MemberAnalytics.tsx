"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import {
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
} from "recharts";
import type { GenderDatum, StatusDatum } from "@/lib/types";

/* -------------------------------------------------------------------------- */
/*  Brand palette (mirrors styles/tokens.css)                                  */
/* -------------------------------------------------------------------------- */

const COLOR = {
  primary: "#1F82C7",
  amber: "#F6A91B",
  success: "#16A34A",
  danger: "#DC2626",
  muted: "#94A3B8",
} as const;

const GENDER_COLOR: Record<string, string> = {
  male: COLOR.primary,
  female: COLOR.amber,
  unspecified: COLOR.muted,
};

const STATUS_COLOR: Record<string, string> = {
  active: COLOR.success,
  pending: COLOR.amber,
  suspended: COLOR.danger,
  cancelled: COLOR.muted,
  expired: COLOR.muted,
};

const tooltipStyle = {
  background: "var(--surface)",
  border: "1px solid var(--border)",
  borderRadius: 8,
  fontSize: "var(--fs-sm)",
  boxShadow: "var(--sh-sm)",
} as const;

interface Datum {
  name: string;
  value: number;
  color: string;
}

export interface MemberAnalyticsProps {
  byGender: GenderDatum[];
  byStatus: StatusDatum[];
}

/**
 * Owner dashboard member analytics: a gender-distribution donut plus a
 * subscription-status donut for the owner's own workspace. Reuses the recharts
 * donut pattern + brand palette from the admin analytics.
 */
export function MemberAnalytics({ byGender, byStatus }: MemberAnalyticsProps) {
  const t = useTranslations("owner.analytics");
  const tg = useTranslations("common.gender");
  const ts = useTranslations("owner.memberStatus");

  const genderData = useMemo<Datum[]>(
    () =>
      byGender
        .map((row) => ({
          name: tg(row.label),
          value: row.value,
          color: GENDER_COLOR[row.label] ?? COLOR.primary,
        }))
        .filter((d) => d.value > 0),
    [byGender, tg],
  );

  const statusData = useMemo<Datum[]>(
    () =>
      byStatus
        .map((row) => ({
          name: ts(row.label),
          value: row.value,
          color: STATUS_COLOR[row.label] ?? COLOR.primary,
        }))
        .filter((d) => d.value > 0),
    [byStatus, ts],
  );

  return (
    <div className="grid-2-cards">
      <section className="card card-pad stack" style={{ gap: 12 }}>
        <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
          {t("byGenderTitle")}
        </h3>
        <Donut data={genderData} emptyLabel={t("noData")} />
      </section>

      <section className="card card-pad stack" style={{ gap: 12 }}>
        <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
          {t("byStatusTitle")}
        </h3>
        <Donut data={statusData} emptyLabel={t("noData")} />
      </section>
    </div>
  );
}

/** A donut chart with an empty fallback. */
function Donut({ data, emptyLabel }: { data: Datum[]; emptyLabel: string }) {
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
    <div style={{ width: "100%", height: 220 }}>
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={data}
            dataKey="value"
            nameKey="name"
            innerRadius={55}
            outerRadius={85}
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
