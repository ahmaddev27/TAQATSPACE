"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { FileDropzone } from "@/components/ui/FileDropzone";
import { Segmented } from "@/components/ui/Segmented";
import { Stepper } from "@/components/ui/Stepper";
import { commitImport, previewImport } from "@/lib/actions/financeImports";
import {
  IMPORT_TYPES,
  type ImportBatch,
  type ImportRowError,
  type ImportType,
} from "@/lib/types/financeImports";

const XLSX = ".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

/** Three steps: upload a file, read the row-by-row check, then import it all at once. */
export function ImportWizard() {
  const t = useTranslations("finance.import");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [type, setType] = useState<ImportType>("catalog_items");
  const [file, setFile] = useState<File | null>(null);
  const [batch, setBatch] = useState<ImportBatch | null>(null);
  const [imported, setImported] = useState<ImportBatch | null>(null);

  const fail = (body: string) => toast({ tone: "err", title: t("error"), body });

  const reset = () => {
    setFile(null);
    setBatch(null);
    setImported(null);
  };

  const onCheck = () => {
    if (!file) return fail(t("noFile"));
    const form = new FormData();
    form.set("type", type);
    form.set("file", file);
    startTransition(async () => {
      const res = await previewImport(form);
      if (res.ok) setBatch(res.data);
      else fail(res.errors?.file?.[0] ?? res.message);
    });
  };

  const onCommit = () => {
    if (!batch) return;
    startTransition(async () => {
      const res = await commitImport(batch.id);
      if (res.ok) setImported(res.data);
      else fail(res.message);
    });
  };

  const columns: DataTableColumn<ImportRowError>[] = [
    { id: "row", header: t("errorCols.row"), num: true, cell: (e) => e.row },
    { id: "column", header: t("errorCols.column"), cell: (e) => <span className="ltr">{e.column ?? "—"}</span> },
    { id: "message", header: t("errorCols.message"), cell: (e) => e.message },
  ];

  const step = imported ? 2 : batch ? 1 : 0;

  return (
    <section className="card card-pad stack" style={{ gap: 16 }}>
      <Stepper steps={[t("steps.upload"), t("steps.preview"), t("steps.done")]} current={step} />

      {step === 0 && (
        <div className="stack" style={{ gap: 14 }}>
          <Segmented
            items={IMPORT_TYPES.map((id) => ({ id, label: t(`types.${id}`) }))}
            value={type}
            onChange={(id) => setType(id as ImportType)}
          />
          <p className="muted" style={{ margin: 0 }}>{t(`typeHint.${type}`)}</p>
          <p className="muted" style={{ margin: 0 }}>{t("rules")}</p>
          <FileDropzone
            value={file}
            onChange={setFile}
            accept={XLSX}
            icon="upload"
            label={t("dropLabel")}
            hint={t("dropHint")}
            clearLabel={t("clear")}
          />
          <div className="row wrap" style={{ gap: 8 }}>
            <Button variant="primary" loading={pending} onClick={onCheck}>
              {pending ? t("checking") : t("check")}
            </Button>
            <a className="btn btn-ghost" href={`/api/owner/finance/imports/template/${type}`}>
              {t("template")}
            </a>
          </div>
        </div>
      )}

      {step === 1 && batch && (
        <div className="stack" style={{ gap: 14 }}>
          {batch.can_commit ? (
            <Alert tone="success" title={t("okTitle")}>
              {t("rows", { count: batch.row_count })}. {t("okBody")}
            </Alert>
          ) : (
            <>
              <Alert tone="danger" title={t("errorsTitle", { count: batch.error_count })}>
                {t("errorsBody")}
              </Alert>
              <DataTable columns={columns} rows={batch.errors} rowKey={(e) => `${e.row}-${e.column}-${e.message}`} />
              {batch.error_count > batch.errors.length && (
                <p className="muted" style={{ margin: 0 }}>{t("moreErrors", { shown: batch.errors.length })}</p>
              )}
            </>
          )}
          <div className="row wrap" style={{ gap: 8 }}>
            {batch.can_commit && (
              <Button variant="primary" loading={pending} onClick={onCommit}>
                {pending ? t("committing") : t("commit")}
              </Button>
            )}
            <Button variant="ghost" onClick={reset}>{t("back")}</Button>
          </div>
        </div>
      )}

      {step === 2 && imported && (
        <div className="stack" style={{ gap: 14 }}>
          <Alert tone="success" title={t("doneTitle")}>
            {t("doneBody", { count: imported.row_count })}
          </Alert>
          <div>
            <Button variant="secondary" onClick={reset}>{t("another")}</Button>
          </div>
        </div>
      )}
    </section>
  );
}
