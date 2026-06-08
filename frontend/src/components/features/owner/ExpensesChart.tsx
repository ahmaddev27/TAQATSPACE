"use client";

import { useMemo } from "react";
import { useLocale, useTranslations } from "next-intl";
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import type { ExpenseMonthlyPoint } from "@/lib/types/management";
import { money } from "./format";

/** Brand palette (mirrors styles/tokens.css). */
const COLOR = {
  primary: "#1F82C7",
} as const;

const AXIS = {
  stroke: "var(--text-3)",
  fontSize: 11,
  fontFamily: "var(--font-num)",
} as const;

const tooltipStyle = {
  background: "var(--surface)",
  border: "1px solid var(--border)",
  borderRadius: 8,
  fontSize: "var(--fs-sm)",
  boxShadow: "var(--sh-sm)",
} as const;

export interface ExpensesChartProps {
  data: ExpenseMonthlyPoint[];
}

/**
 * Trailing-12-month operating-spend bar chart for the owner expenses page.
 * Reuses the recharts bar-chart pattern + brand palette from the admin reports
 * dashboard. Month labels are localized; recharts mirrors automatically for RTL.
 */
export function ExpensesChart({ data }: ExpensesChartProps) {
  const t = useTranslations("management.expenses");
  const locale = useLocale();

  const monthLabel = useMemo(() => {
    const fmt = new Intl.DateTimeFormat(locale, { month: "short" });
    return (month: string): string => {
      const [y, m] = month.split("-");
      const idx = Number(m) - 1;
      if (!Number.isFinite(idx)) return month;
      return fmt.format(new Date(Number(y), idx, 1));
    };
  }, [locale]);

  const chartData = useMemo(
    () =>
      data.map((row) => ({
        label: monthLabel(row.month),
        total: Number(row.total),
      })),
    [data, monthLabel],
  );

  const hasSpend = useMemo(
    () => chartData.some((d) => d.total > 0),
    [chartData],
  );

  return (
    <section className="card card-pad stack" style={{ gap: 12 }}>
      <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
        {t("monthlyChartTitle")}
      </h3>
      {!hasSpend ? (
        <div
          style={{
            height: 240,
            display: "grid",
            placeItems: "center",
            color: "var(--text-3)",
            fontSize: "var(--fs-sm)",
          }}
        >
          {t("monthlyChartEmpty")}
        </div>
      ) : (
        <div style={{ width: "100%", height: 240 }}>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={chartData} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
              <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="label" tick={AXIS} tickLine={false} axisLine={{ stroke: "var(--border)" }} />
              <YAxis tick={AXIS} tickLine={false} axisLine={false} width={56} />
              <Tooltip
                formatter={(value) => [money(Number(value)), t("monthlyChartTitle")]}
                contentStyle={tooltipStyle}
                labelStyle={{ color: "var(--text-2)" }}
                cursor={{ fill: "var(--surface-sunk)" }}
              />
              <Bar dataKey="total" fill={COLOR.primary} radius={[4, 4, 0, 0]} maxBarSize={36} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}
    </section>
  );
}
