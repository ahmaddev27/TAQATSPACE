import { TileLogo } from "@/components/layout/TileLogo";

/** Branded route-transition loader (shown while a page's data resolves). */
export default function Loading() {
  return (
    <div className="brand-loader" role="status" aria-label="Loading">
      <div className="brand-loader-in">
        <TileLogo size={34} />
        <div className="loader-bars" aria-hidden="true">
          <span />
          <span />
          <span />
          <span />
        </div>
      </div>
    </div>
  );
}
