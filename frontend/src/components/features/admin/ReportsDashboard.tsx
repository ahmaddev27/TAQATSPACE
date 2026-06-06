"use client";

import { useMemo } from "react";
import { useTranslations } from "next-intl";
import {
  Area,
  AreaChart,
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
import type { AdminReports } from "@/lib/api/admin";
import { invoiceMoney } from "@/components/features/invoices/format";
import { ExportCsvLink, type ExportType } from "./ExportCsvLink";

/* -------------------------------------------------------------------------- */
/*  Brand palette (mirrors styles/tokens.css)                                  */
/* -------------------------------------------------------------------------- */

const COLOR = {
  primary: "#1F82C7",
  amber: "#F6A91B",
  success: "#16A34A",
  danger: "#DC2626",
  pending: "#F6A91B",
  muted: "#94A3B8",
} as const;

/** Stable colour per status bucket so the same status keeps its colour. */
const STATUS_COLOR: Record<string, string> = {
  paid: COLOR.success,
  active: COLOR.success,
  pending: COLOR.amber,
  pending_verification: COLOR.amber,
  overdue: COLOR.danger,
  cancelled: COLOR.muted,
  suspended: COLOR.danger,
  rejected: COLOR.danger,
  expired: COLOR.muted,
};

const DONUT_COLORS = [
  COLOR.primary,
  COLOR.amber,
  COLOR.success,
  COLOR.danger,
  COLOR.muted,
  "#6366F1",
];

const AXIS = {
  stroke: "var(--text-3)",
  fontSize: 11,
  fontFamily: "var(--font-num)",
} as const;

/** Render `YYYY-MM` as a short month label (e.g. "Jan"). */
function monthLabel(month: string): string {
  const [, m] = month.split("-");
  const idx = Number(m) - 1;
  const names = [
    "Jan", "Feb", "Mar", "Apr", "May", "Jun",
    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
  ];
  return names[idx] ?? month;
}

interface DonutDatum {
  name: string;
  value: number;
  color: string;
}

export interface ReportsDashboardProps {
  data: AdminReports;
}

/** Recharts-driven analytics dashboard for the super-admin reports page. */
export function ReportsDashboard({ data }: ReportsDashboardProps) {
  const t = useTranslations("admin.reports");

  const revenueData = useMemo(
    () =>
      data.revenue_by_month.map((row) => ({
        label: monthLabel(row.month),
        paid: Number(row.paid),
        outstanding: Number(row.outstanding),
      })),
    [data.revenue_by_month],
  );

  const subsData = useMemo(
    () =>
      data.subscriptions_by_month.map((row) => ({
        label: monthLabel(row.month),
        count: row.count,
      })),
    [data.subscriptions_by_month],
  );

  const invoiceDonut = useMemo<DonutDatum[]>(() => {
    const buckets = data.invoices_by_status;
    return (Object.keys(buckets) as Array<keyof typeof buckets>)
      .map((status) => ({
        name: t(`invoiceStatus.${status}`),
        value: buckets[status].count,
        color: STATUS_COLOR[status] ?? COLOR.primary,
      }))
      .filter((d) => d.value > 0);
  }, [data.invoices_by_status, t]);

  const subscriptionDonut = useMemo<DonutDatum[]>(() => {
    const entries = Object.entries(data.subscriptions_by_status);
    return entries
      .map(([status, count], i) => ({
        name: t(`subscriptionStatus.${status}`),
        value: count,
        color: STATUS_COLOR[status] ?? DONUT_COLORS[i % DONUT_COLORS.length],
      }))
      .filter((d) => d.value > 0);
  }, [data.subscriptions_by_status, t]);

  const exports: { type: ExportType; label: string }[] = [
    { type: "invoices", label: t("exports.invoices") },
    { type: "subscriptions", label: t("exports.subscriptions") },
    { type: "users", label: t("exports.users") },
    { type: "workspaces", label: t("exports.workspaces") },
  ];

  return (
    <div className="stack" style={{ gap: 24, marginTop: 8 }}>
      {/* Revenue by month — paid vs outstanding (admin-tracked, not gateway) */}
      <section className="card card-pad stack" style={{ gap: 16 }}>
        <div>
          <h3 className="h3">{t("revenueTitle")}</h3>
          <p className="muted-3" style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}>
            {t("revenueSubtitle")}
          </p>
        </div>
        <div style={{ width: "100%", height: 280 }}>
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={revenueData} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
              <defs>
                <linearGradient id="gradPaid" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor={COLOR.primary} stopOpacity={0.35} />
                  <stop offset="100%" stopColor={COLOR.primary} stopOpacity={0.02} />
                </linearGradient>
                <linearGradient id="gradOutstanding" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor={COLOR.amber} stopOpacity={0.3} />
                  <stop offset="100%" stopColor={COLOR.amber} stopOpacity={0.02} />
                </linearGradient>
              </defs>
              <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="label" tick={AXIS} tickLine={false} axisLine={{ stroke: "var(--border)" }} />
              <YAxis tick={AXIS} tickLine={false} axisLine={false} width={48} />
              <Tooltip
                formatter={(value, name) => [
                  invoiceMoney(Number(value)),
                  name === "paid" ? t("revenuePaid") : t("revenueOutstanding"),
                ]}
                contentStyle={tooltipStyle}
                labelStyle={{ color: "var(--text-2)" }}
              />
              <Legend
                formatter={(value) =>
                  value === "paid" ? t("revenuePaid") : t("revenueOutstanding")
                }
                wrapperStyle={{ fontSize: "var(--fs-sm)" }}
              />
              <Area
                type="monotone"
                dataKey="paid"
                stroke={COLOR.primary}
                strokeWidth={2}
                fill="url(#gradPaid)"
              />
              <Area
                type="monotone"
                dataKey="outstanding"
                stroke={COLOR.amber}
                strokeWidth={2}
                fill="url(#gradOutstanding)"
              />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </section>

      {/* Status donuts: invoices + subscriptions */}
      <div className="grid-2-cards">
        <section className="card card-pad stack" style={{ gap: 12 }}>
          <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
            {t("invoicesByStatus")}
          </h3>
          <StatusDonut data={invoiceDonut} emptyLabel={t("noData")} />
        </section>

        <section className="card card-pad stack" style={{ gap: 12 }}>
          <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
            {t("subscriptionsByStatus")}
          </h3>
          <StatusDonut data={subscriptionDonut} emptyLabel={t("noData")} />
        </section>
      </div>

      {/* New subscriptions per month */}
      <section className="card card-pad stack" style={{ gap: 16 }}>
        <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
          {t("newSubscriptions")}
        </h3>
        <div style={{ width: "100%", height: 240 }}>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={subsData} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
              <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="label" tick={AXIS} tickLine={false} axisLine={{ stroke: "var(--border)" }} />
              <YAxis tick={AXIS} tickLine={false} axisLine={false} width={36} allowDecimals={false} />
              <Tooltip
                formatter={(value) => [value, t("newSubscriptions")]}
                contentStyle={tooltipStyle}
                labelStyle={{ color: "var(--text-2)" }}
                cursor={{ fill: "var(--surface-sunk)" }}
              />
              <Bar dataKey="count" fill={COLOR.primary} radius={[4, 4, 0, 0]} maxBarSize={36} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </section>

      {/* Top workspaces leaderboard */}
      <section className="card card-pad stack" style={{ gap: 12 }}>
        <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
          {t("topWorkspaces")}
        </h3>
        {data.top_workspaces.length === 0 ? (
          <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("noData")}
          </p>
        ) : (
          <div className="stack" style={{ gap: 0 }}>
            <div
              className="report-row muted-3"
              style={{ fontSize: "var(--fs-xs)", fontWeight: 600 }}
            >
              <span style={{ width: 26 }} />
              <span style={{ flex: 1 }}>{t("colWorkspace")}</span>
              <span className="cell-num" style={{ width: 120, textAlign: "end" }}>
                {t("colPaidTotal")}
              </span>
              <span className="cell-num" style={{ width: 90, textAlign: "end" }}>
                {t("colActiveSubs")}
              </span>
            </div>
            {data.top_workspaces.map((ws, i) => (
              <div className="report-row" key={ws.workspace_id}>
                <span className={`rank${i < 3 ? " top" : ""}`}>{i + 1}</span>
                <span style={{ flex: 1, fontWeight: 500 }}>{ws.name}</span>
                <span
                  className="cell-num"
                  style={{ width: 120, textAlign: "end", fontWeight: 600 }}
                >
                  {invoiceMoney(ws.paid_total)}
                </span>
                <span className="cell-num" style={{ width: 90, textAlign: "end" }}>
                  {ws.active_subscriptions}
                </span>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Exports */}
      <section className="card card-pad stack" style={{ gap: 12 }}>
        <div>
          <h3 className="h3" style={{ fontSize: "var(--fs-md)" }}>
            {t("exports.title")}
          </h3>
          <p className="muted-3" style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}>
            {t("exports.subtitle")}
          </p>
        </div>
        <div className="grid-export">
          {exports.map((e) => (
            <ExportCsvLink key={e.type} type={e.type} label={e.label} variant="card" />
          ))}
        </div>
      </section>
    </div>
  );
}

/** Shared tooltip surface styling. */
const tooltipStyle = {
  background: "var(--surface)",
  border: "1px solid var(--border)",
  borderRadius: 8,
  fontSize: "var(--fs-sm)",
  boxShadow: "var(--sh-sm)",
} as const;

/** A donut chart for a status breakdown, with an empty fallback. */
function StatusDonut({
  data,
  emptyLabel,
}: {
  data: DonutDatum[];
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
