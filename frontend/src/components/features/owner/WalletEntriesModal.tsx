"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Modal } from "@/components/ui/Modal";
import { loadWalletEntries } from "@/lib/actions/financeWallet";
import { formatDateTime } from "@/lib/dates";
import type { WalletEntry, WalletRow } from "@/lib/types/financeWallet";
import { ReverseEntryModal } from "./ReverseEntryModal";
import { money } from "./format";

interface Props {
  row: WalletRow;
  onClose: () => void;
}

type Load = { state: "loading" } | { state: "error"; message: string } | { state: "ready"; entries: WalletEntry[] };

/** The ledger of one customer's wallet, with a reverse action on entries that allow it. */
export function WalletEntriesModal({ row, onClose }: Props) {
  const t = useTranslations("finance.wallet");
  const [load, setLoad] = useState<Load>({ state: "loading" });
  const [reversing, setReversing] = useState<WalletEntry | null>(null);

  const refresh = useCallback(
    () =>
      loadWalletEntries(row.customer.id).then((res) =>
        setLoad(res.ok ? { state: "ready", entries: res.data.items } : { state: "error", message: res.message }),
      ),
    [row.customer.id],
  );

  useEffect(() => {
    // Set state only once the request answers, never synchronously in the effect.
    void refresh();
  }, [refresh]);

  const label = (e: WalletEntry) => t(e.related_type === "wallet_entry" ? "entryTypes.reversal" : `entryTypes.${e.type}`);

  const columns: DataTableColumn<WalletEntry>[] = [
    { id: "type", header: t("fields.type"), cell: (e) => label(e) },
    { id: "amount", header: t("fields.amount"), num: true, cell: (e) => <span className="ltr">{money(e.amount)}</span> },
    { id: "date", header: t("fields.date"), cell: (e) => (e.created_at ? formatDateTime(e.created_at) : "—") },
    { id: "reason", header: t("fields.reason"), cell: (e) => e.reason ?? "—" },
    {
      id: "actions",
      header: "",
      cell: (e) => (
        <div className="row wrap" style={{ gap: 6 }}>
          {e.reversed && <Badge tone="neutral">{t("statuses.reversed")}</Badge>}
          {e.reversible && !e.reversed && (
            <Button variant="ghost" size="sm" onClick={() => setReversing(e)}>{t("reverse")}</Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <Modal
      title={t("entriesTitle", { name: row.customer.name })}
      icon="wallet"
      onClose={onClose}
      footer={<Button variant="ghost" onClick={onClose}>{t("close")}</Button>}
    >
      {load.state === "loading" && <p className="muted" role="status" style={{ margin: 0 }}>{t("loading")}</p>}
      {load.state === "error" && <p role="alert" style={{ margin: 0, color: "var(--danger)" }}>{load.message}</p>}
      {load.state === "ready" && (
        <DataTable
          columns={columns}
          rows={load.entries}
          rowKey={(e) => e.id}
          empty={<p className="muted" style={{ margin: 0 }}>{t("entriesEmpty")}</p>}
        />
      )}
      {reversing && (
        <ReverseEntryModal
          customerId={row.customer.id}
          entry={reversing}
          onClose={() => setReversing(null)}
          onReversed={() => {
            setReversing(null);
            void refresh();
          }}
        />
      )}
    </Modal>
  );
}
