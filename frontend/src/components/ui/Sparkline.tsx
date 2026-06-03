export interface SparklineProps {
  data: number[];
  w?: number;
  h?: number;
}

/** Tiny inline trend line (no axes). Ports the prototype `Sparkline`. */
export function Sparkline({ data, w = 180, h = 34 }: SparklineProps) {
  if (data.length === 0) return null;
  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;
  const points = data
    .map((v, i) => {
      const x = (i / (data.length - 1 || 1)) * w;
      const y = h - ((v - min) / range) * (h - 4) - 2;
      return `${x},${y}`;
    })
    .join(" ");

  return (
    <svg
      className="chart"
      viewBox={`0 0 ${w} ${h}`}
      style={{ height: h, marginTop: 4 }}
      preserveAspectRatio="none"
    >
      <polyline className="line" style={{ strokeWidth: 2 }} points={points} />
    </svg>
  );
}
