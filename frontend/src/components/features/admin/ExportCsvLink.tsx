import { Icon } from "@/components/ui/Icon";

/** Datasets exposed by the admin CSV export route handler. */
export type ExportType = "invoices" | "subscriptions" | "users" | "workspaces";

export interface ExportCsvLinkProps {
  type: ExportType;
  label: string;
  /** Optional list filters carried through to the backend export. */
  query?: Record<string, string | undefined>;
  /** Visual variant: a toolbar button (default) or a full card row. */
  variant?: "button" | "card";
  className?: string;
}

/** Serialise filters to a query string, skipping empty values. */
function toQuery(query: ExportCsvLinkProps["query"]): string {
  if (!query) return "";
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value == null || value === "") continue;
    params.set(key, value);
  }
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

/**
 * A plain anchor that downloads a CSV from the same-origin export route
 * handler, which proxies the request to the backend with the admin bearer.
 * Using `<a download>` (not fetch) lets the browser stream the file directly.
 */
export function ExportCsvLink({
  type,
  label,
  query,
  variant = "button",
  className,
}: ExportCsvLinkProps) {
  const href = `/api/admin/exports/${type}${toQuery(query)}`;
  const base =
    variant === "card"
      ? "card card-pad row"
      : "btn btn-secondary btn-sm";

  return (
    <a
      className={`${base} ${className ?? ""}`.trim()}
      href={href}
      download
      style={
        variant === "card"
          ? {
              gap: 10,
              alignItems: "center",
              textDecoration: "none",
              color: "var(--text)",
            }
          : undefined
      }
    >
      <span className={variant === "card" ? "st-ico" : undefined}>
        <Icon name="download" size={variant === "card" ? 20 : 15} />
      </span>
      <span style={{ fontSize: "var(--fs-sm)", fontWeight: 500 }}>{label}</span>
    </a>
  );
}
