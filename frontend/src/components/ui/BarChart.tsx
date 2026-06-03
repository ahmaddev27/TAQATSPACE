export interface BarChartPoint {
  /** X-axis label. */
  label: string;
  v: number;
}

export interface BarChartProps {
  data: BarChartPoint[];
  h?: number;
  /** Tint the final bar with the accent colour. */
  accentLast?: boolean;
}

/** Vertical bar chart matching the prototype SVG. */
export function BarChart({ data, h = 220, accentLast }: BarChartProps) {
  if (data.length === 0) {
    return <svg className="chart" viewBox={`0 0 560 ${h}`} style={{ height: h }} />;
  }

  const w = 560;
  const pad = 30;
  const max = Math.max(...data.map((d) => d.v)) * 1.1 || 1;
  const gap = (w - pad * 2) / data.length;
  const bw = gap * 0.56;

  return (
    <svg
      className="chart"
      viewBox={`0 0 ${w} ${h}`}
      style={{ width: "100%", height: h }}
    >
      {[0, 0.5, 1].map((t) => (
        <line
          key={t}
          className="grid-line"
          x1={pad}
          x2={w - pad}
          y1={20 + t * (h - 44)}
          y2={20 + t * (h - 44)}
        />
      ))}
      {data.map((d, i) => {
        const bh = (d.v / max) * (h - 44);
        const bx = pad + gap * i + (gap - bw) / 2;
        const isAccent = accentLast && i === data.length - 1;
        return (
          <g key={i}>
            <rect
              className={`bar ${isAccent ? "amber" : ""}`.trim()}
              x={bx}
              y={h - 24 - bh}
              width={bw}
              height={Math.max(0, bh)}
              rx="6"
            />
            <text
              className="chart-axis"
              x={bx + bw / 2}
              y={h - 8}
              textAnchor="middle"
            >
              {d.label}
            </text>
          </g>
        );
      })}
    </svg>
  );
}
