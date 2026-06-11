/**
 * App Router re-mounts this on every navigation within the dashboard group, so
 * the `.u-route` one-shot entrance plays per route change (reduced-motion safe).
 */
export default function DashboardTemplate({
  children,
}: {
  children: React.ReactNode;
}) {
  return <div className="u-route">{children}</div>;
}
