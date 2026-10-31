import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { adminApprovals } from "@/lib/api/approvals";
import { ApprovalsTable } from "@/components/features/admin/ApprovalsTable";
import type { ApprovalStatus } from "@/lib/types/approvals";

export const dynamic = "force-dynamic";

const STATUSES: readonly ApprovalStatus[] = ["pending", "approved", "rejected", "cancelled"];

export default async function AdminApprovalsPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "approve_requests");
  const t = await getTranslations("approvals");
  const query = await searchParams;

  const rawStatus = typeof query.status === "string" ? query.status : "pending";
  const status = STATUSES.find((s) => s === rawStatus) ?? "pending";
  const page = Number(typeof query.page === "string" ? query.page : 1) || 1;

  const { requests, meta } = await adminApprovals({ status, page, per_page: 20 });

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
        <ApprovalsTable requests={requests} meta={meta} status={status} />
      </div>
    </div>
  );
}
