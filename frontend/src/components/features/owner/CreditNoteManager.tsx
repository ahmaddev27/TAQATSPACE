"use client";

import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { formatDate } from "@/lib/dates";
import type { CreditNote } from "@/lib/types/financeCreditNotes";
import { money } from "./format";

interface Props {
  creditNotes: CreditNote[];
}

/** Owner credit notes: what was credited on which invoice and why, with the PDF. */
export function CreditNoteManager({ creditNotes }: Props) {
  const t = useTranslations("finance.creditNotes");

  const columns: DataTableColumn<CreditNote>[] = [
    { id: "number", header: t("fields.number"), cell: (c) => <span className="ltr">{c.number}</span> },
    { id: "invoice", header: t("fields.invoice"), cell: (c) => <span className="ltr">{c.invoice_number ?? "—"}</span> },
    { id: "customer", header: t("fields.customer"), cell: (c) => c.customer?.name ?? "—" },
    { id: "amount", header: t("fields.amount"), num: true, cell: (c) => money(c.amount) },
    { id: "reason", header: t("fields.reason"), cell: (c) => c.reason },
    { id: "issuedAt", header: t("fields.issuedAt"), cell: (c) => (c.issued_at ? formatDate(c.issued_at) : "—") },
    {
      id: "actions",
      header: "",
      cell: (c) => (
        <a className="btn btn-ghost btn-sm" href={`/api/credit-notes/${c.id}/pdf`} download rel="noopener">
          <Icon name="download" size={15} />
          {t("downloadPdf")}
        </a>
      ),
    },
  ];

  return (
    <section className="card card-pad stack" style={{ gap: 16 }}>
      <DataTable
        columns={columns}
        rows={creditNotes}
        rowKey={(c) => c.id}
        empty={<p className="muted" style={{ margin: 0 }}>{t("empty")}</p>}
      />
    </section>
  );
}
