"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { usePathname, useRouter } from "@/i18n/navigation";
import { StatTile } from "@/components/ui/StatTile";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { money } from "@/components/features/owner/format";
import type { PosReport } from "@/lib/types/pos";

interface Props {
  report: PosReport;
}

/**
 * Shared POS sales-report view (owner + cashier). Renders headline KPIs, a date
 * range filter that reloads the page with `?from=&to=`, and simple breakdown
 * tables (top products, by cashier, by method) plus a refunds summary. Purely
 * presentational over the pre-fetched `PosReport`; the reader owns permission
 * handling, so this only ever receives an allowed payload.
 */
export function PosReports({ report }: Props) {
  const t = useTranslations("posReports");
  const router = useRouter();
  const pathname = usePathname();
  const [from, setFrom] = useState(report.range.from);
  const [to, setTo] = useState(report.range.to);

  const applyRange = (e: FormEvent) => {
    e.preventDefault();
    const params = new URLSearchParams();
    if (from) params.set("from", from);
    if (to) params.set("to", to);
    const qs = params.toString();
    router.push(qs ? `${pathname}?${qs}` : pathname);
  };

  return (
    <div className="stack" style={{ gap: 24 }}>
      <form className="card card-pad" onSubmit={applyRange}>
        <div
          style={{
            display: "grid",
            gridTemplateColumns:
              "repeat(auto-fit, minmax(min(100%, 180px), 1fr))",
            gap: 12,
            alignItems: "flex-end",
          }}
        >
          <Field label={t("from")} className="ltr" htmlFor="pos-report-from">
            <Input
              id="pos-report-from"
              type="date"
              value={from}
              max={to || undefined}
              onChange={(e) => setFrom(e.target.value)}
            />
          </Field>
          <Field label={t("to")} className="ltr" htmlFor="pos-report-to">
            <Input
              id="pos-report-to"
              type="date"
              value={to}
              min={from || undefined}
              onChange={(e) => setTo(e.target.value)}
            />
          </Field>
          <Button type="submit" variant="secondary" icon="filter" block>
            {t("apply")}
          </Button>
        </div>
      </form>

      <div className="grid-stats">
        <StatTile icon="wallet" label={t("totals.sales")} value={money(report.totals.sales)} />
        <StatTile icon="receipt" label={t("totals.orders")} value={report.totals.orders} />
        <StatTile icon="chart" label={t("totals.avg")} value={money(report.totals.avg)} />
        <StatTile
          icon="refresh"
          amber={report.refunds.count > 0}
          label={t("refunds.title")}
          value={money(report.refunds.total)}
          foot={t("refunds.count", { count: report.refunds.count })}
        />
      </div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))",
          gap: 20,
          alignItems: "start",
        }}
      >
        <ReportTable
          heading={t("topProducts.title")}
          empty={t("empty")}
          columns={[t("topProducts.name"), t("topProducts.qty"), t("topProducts.total")]}
          rows={report.top_products.map((p) => [p.name, String(p.qty), money(p.total)])}
        />
        <ReportTable
          heading={t("byCashier.title")}
          empty={t("empty")}
          columns={[t("byCashier.name"), t("byCashier.orders"), t("byCashier.total")]}
          rows={report.by_cashier.map((c) => [c.name, String(c.orders), money(c.total)])}
        />
        <ReportTable
          heading={t("byMethod.title")}
          empty={t("empty")}
          columns={[t("byMethod.method"), t("byMethod.total")]}
          rows={report.by_method.map((m) => [t(`method.${m.method}`), money(m.total)])}
        />
      </div>
    </div>
  );
}

/** A titled table with a header row and numeric-aligned body; empty-state aware. */
function ReportTable({
  heading,
  columns,
  rows,
  empty,
}: {
  heading: string;
  columns: string[];
  rows: string[][];
  empty: string;
}) {
  return (
    <section className="card card-pad stack" style={{ gap: 12 }}>
      <h3 className="h3" style={{ margin: 0 }}>{heading}</h3>
      {rows.length === 0 ? (
        <p className="muted" style={{ margin: 0 }}>{empty}</p>
      ) : (
        <div style={{ overflowX: "auto" }}>
          <table className="tbl" style={{ width: "100%" }}>
            <thead>
              <tr>
                {columns.map((c, i) => (
                  <th key={c} style={{ textAlign: i === 0 ? "start" : "end" }}>
                    {c}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, r) => (
                <tr key={r}>
                  {row.map((cell, i) => (
                    <td
                      key={i}
                      className={i === 0 ? undefined : "tnum"}
                      style={{ textAlign: i === 0 ? "start" : "end" }}
                    >
                      {cell}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
