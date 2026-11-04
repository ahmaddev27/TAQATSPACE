import { notFound } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { ForbiddenError } from "@/lib/api";
import { ownerRouterId, routerApiLogs } from "@/lib/api/routers";
import { ApiLogsTable } from "@/components/features/owner/ApiLogsTable";
import { DiagnosticsPanel } from "@/components/features/owner/DiagnosticsPanel";
import type { RouterApiLogResult } from "@/lib/types/router";

export const dynamic = "force-dynamic";

const FILTER_KEYS = ["from", "to", "response_code", "error"] as const;

/** A workspace without the `network` flag gets a 403 from the API: the page does not exist for it. */
async function load(query: Record<string, string | undefined>): Promise<{ id: string; result: RouterApiLogResult } | null> {
  try {
    const id = await ownerRouterId();
    if (id === null) return null;

    const filters = Object.fromEntries(FILTER_KEYS.map((key) => [key, query[key]]));
    const result = await routerApiLogs(id, { ...filters, page: Number(query.page) || 1, per_page: 20 });
    return { id, result };
  } catch (error) {
    if (error instanceof ForbiddenError) notFound();
    throw error;
  }
}

export default async function ApiDiagnosticsPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("integration.diagnosticsPage");
  const raw = await searchParams;
  const query = Object.fromEntries(
    Object.entries(raw).map(([key, value]) => [key, typeof value === "string" ? value : undefined]),
  );

  const loaded = await load(query);

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
      </div>

      {loaded === null ? (
        <div className="card card-pad muted">{t("noRouter")}</div>
      ) : (
        <div className="stack" style={{ gap: 18 }}>
          <div className="card card-pad stack" style={{ gap: 12 }}>
            <h4 className="h4">{t("checkTitle")}</h4>
            <DiagnosticsPanel routerId={loaded.id} />
          </div>
          <div className="card card-pad stack" style={{ gap: 12 }}>
            <h4 className="h4">{t("logTitle")}</h4>
            <ApiLogsTable logs={loaded.result.logs} meta={loaded.result.meta} />
          </div>
        </div>
      )}
    </div>
  );
}
