/**
 * Per-route entrance for the public marketing pages (re-mounts on navigation).
 */
export default function PublicTemplate({
  children,
}: {
  children: React.ReactNode;
}) {
  return <div className="u-route">{children}</div>;
}
