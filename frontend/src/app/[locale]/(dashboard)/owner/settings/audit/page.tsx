import { getTranslations, setRequestLocale } from "next-intl/server";
import { ownerAuditLogs } from "@/lib/api/audit";
import { AuditLogTable } from "@/components/features/owner/AuditLogTable";

export const dynamic = "force-dynamic";

const FILTER_KEYS = ["actor_id", "action", "from", "to", "subject_type", "subject_id", "reason"] as const;

export default async function OwnerAuditLogPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("audit");
  const query = await searchParams;

  const pick = (key: string): string | undefined => {
    const value = query[key];
    return typeof value === "string" ? value : undefined;
  };

  const filters = Object.fromEntries(FILTER_KEYS.map((key) => [key, pick(key)]));
  const page = Number(pick("page")) || 1;

  const { logs, meta } = await ownerAuditLogs({ ...filters, page, per_page: 20 });

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

      <div style={{ marginTop: 16 }}>
        <AuditLogTable logs={logs} meta={meta} />
      </div>
    </div>
  );
}
