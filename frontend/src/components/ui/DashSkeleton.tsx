/**
 * Lightweight content-area placeholder shown as a route-segment Suspense
 * fallback (`loading.tsx`) while a dashboard page resolves on the server.
 *
 * It mirrors the real page chrome (`.page` → `.page-head` + content) so the
 * shell stays visually stable during navigation — only the inner content
 * swaps to a shimmering skeleton instead of appearing frozen. Reuses the
 * existing `.skel` shimmer token; RTL-safe (no directional offsets) and
 * announced to assistive tech via `role="status"`.
 */
export interface DashSkeletonProps {
  /** Number of KPI tiles to mock in the stats row. */
  stats?: number;
  /** Number of content cards below the stats row. */
  cards?: number;
}

export function DashSkeleton({ stats = 4, cards = 2 }: DashSkeletonProps) {
  return (
    <div className="page" role="status" aria-busy="true" aria-live="polite">
      <div className="page-head">
        <div className="stack" style={{ gap: 10 }}>
          <span className="skel" style={{ width: 220, height: 26 }} />
          <span className="skel" style={{ width: 300, height: 14 }} />
        </div>
      </div>

      {stats > 0 && (
        <div className="grid-stats" style={{ marginBottom: "var(--s-6)" }}>
          {Array.from({ length: stats }).map((_, i) => (
            <div
              key={i}
              className="card"
              style={{ padding: "var(--s-5)" }}
              aria-hidden="true"
            >
              <span
                className="skel"
                style={{ width: "55%", height: 12, display: "block" }}
              />
              <span
                className="skel"
                style={{
                  width: "70%",
                  height: 28,
                  display: "block",
                  marginTop: 14,
                }}
              />
            </div>
          ))}
        </div>
      )}

      <div className="stack" style={{ gap: "var(--s-4)" }} aria-hidden="true">
        {Array.from({ length: cards }).map((_, i) => (
          <div key={i} className="card" style={{ padding: "var(--s-6)" }}>
            <span
              className="skel"
              style={{ width: 160, height: 18, display: "block" }}
            />
            <div className="stack" style={{ gap: 12, marginTop: 18 }}>
              {Array.from({ length: 4 }).map((_, r) => (
                <span
                  key={r}
                  className="skel"
                  style={{
                    width: `${90 - r * 12}%`,
                    height: 14,
                    display: "block",
                  }}
                />
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
