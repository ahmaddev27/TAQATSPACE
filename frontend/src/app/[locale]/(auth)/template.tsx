/**
 * Per-route entrance for the auth pages (re-mounts on navigation).
 */
export default function AuthTemplate({
  children,
}: {
  children: React.ReactNode;
}) {
  return <div className="u-route">{children}</div>;
}
