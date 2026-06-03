import { Icon } from "@/components/ui/Icon";

export interface DownloadPdfLinkProps {
  invoiceId: string;
  label: string;
}

/**
 * Download link to the same-origin PDF route handler, which attaches the
 * bearer token server-side. A plain `<a>` (not the locale `Link`) keeps the URL
 * un-prefixed so it hits the Route Handler under `/api`, and `download` asks the
 * browser to save rather than navigate.
 */
export function DownloadPdfLink({ invoiceId, label }: DownloadPdfLinkProps) {
  return (
    <a
      className="btn btn-ghost btn-sm"
      href={`/api/invoices/${invoiceId}/pdf`}
      download
      rel="noopener"
    >
      <Icon name="download" size={15} />
      {label}
    </a>
  );
}
