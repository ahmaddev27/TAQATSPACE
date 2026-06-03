export interface GaugeProps {
  /** 0–100. */
  value: number;
  size?: number;
  label?: string;
}

/** Circular percentage gauge. Ports the prototype `Gauge`. */
export function Gauge({ value, size = 140, label }: GaugeProps) {
  const r = size / 2 - 12;
  const c = 2 * Math.PI * r;
  const clamped = Math.max(0, Math.min(100, value));
  const offset = c * (1 - clamped / 100);

  return (
    <div className="gauge" style={{ width: size, textAlign: "center" }}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
        <circle
          className="gauge-track"
          cx={size / 2}
          cy={size / 2}
          r={r}
          strokeWidth="11"
        />
        <circle
          className="gauge-fill"
          cx={size / 2}
          cy={size / 2}
          r={r}
          strokeWidth="11"
          strokeDasharray={c}
          strokeDashoffset={offset}
          transform={`rotate(-90 ${size / 2} ${size / 2})`}
        />
        <text
          x="50%"
          y="48%"
          textAnchor="middle"
          className="tnum"
          style={{ fontSize: size * 0.22, fontWeight: 700, fill: "var(--text)" }}
        >
          {clamped}%
        </text>
        {label && (
          <text
            x="50%"
            y="64%"
            textAnchor="middle"
            style={{ fontSize: size * 0.1, fill: "var(--text-3)" }}
          >
            {label}
          </text>
        )}
      </svg>
    </div>
  );
}
