"use client";

import { useId } from "react";

export interface LineChartPoint {
  /** X-axis label. */
  label: string;
  /** Value. */
  v: number;
}

export interface LineChartProps {
  data: LineChartPoint[];
  h?: number;
}

/**
 * Area + line chart matching the prototype SVG. Renders nothing meaningful for
 * empty data. Uses a unique gradient id so multiple charts coexist.
 */
export function LineChart({ data, h = 220 }: LineChartProps) {
  const gradId = useId().replace(/:/g, "");

  if (data.length === 0) {
    return <svg className="chart" viewBox={`0 0 560 ${h}`} style={{ height: h }} />;
  }

  const w = 560;
  const pad = 34;
  const vals = data.map((d) => d.v);
  const max = Math.max(...vals) * 1.15 || 1;
  const min = 0;
  const span = data.length - 1 || 1;

  const x = (i: number) => pad + (i / span) * (w - pad * 2);
  const y = (v: number) => h - 26 - ((v - min) / (max - min)) * (h - 50);

  const line = data.map((d, i) => `${x(i)},${y(d.v)}`).join(" ");
  const area = `${pad},${h - 26} ${line} ${w - pad},${h - 26}`;

  return (
    <svg
      className="chart"
      viewBox={`0 0 ${w} ${h}`}
      style={{ width: "100%", height: h }}
    >
      <defs>
        <linearGradient id={gradId} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="var(--primary)" stopOpacity="0.18" />
          <stop offset="100%" stopColor="var(--primary)" stopOpacity="0" />
        </linearGradient>
      </defs>
      {[0, 0.25, 0.5, 0.75, 1].map((t) => (
        <line
          key={t}
          className="grid-line"
          x1={pad}
          x2={w - pad}
          y1={26 + t * (h - 52)}
          y2={26 + t * (h - 52)}
        />
      ))}
      <polygon style={{ fill: `url(#${gradId})` }} points={area} />
      <polyline className="line" points={line} />
      {data.map((d, i) => (
        <circle key={i} className="dot" cx={x(i)} cy={y(d.v)} r="3.5" />
      ))}
      {data.map((d, i) => (
        <text
          key={i}
          className="chart-axis"
          x={x(i)}
          y={h - 8}
          textAnchor="middle"
        >
          {d.label}
        </text>
      ))}
    </svg>
  );
}
