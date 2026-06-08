import { getTranslations, setRequestLocale } from "next-intl/server";
import { requireAdminPermission } from "@/lib/admin-guard";
import { getAllAdminUsers } from "@/lib/api/admin";
import { AdminBroadcastForm } from "@/components/features/admin/AdminBroadcastForm";
import type { RecipientOption } from "@/components/features/messaging/BroadcastComposer";

export const dynamic = "force-dynamic";

export default async function AdminBroadcastPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  await requireAdminPermission(locale, "manage_messaging");
  const t = await getTranslations("broadcast.admin");

  // The "specific" picker reuses the existing admin users directory. A read
  // failure must not break the page — fall back to an empty roster (the picker
  // simply shows no options; "all"/"segment" still work).
  let recipients: RecipientOption[] = [];
  try {
    const users = await getAllAdminUsers();
    recipients = users.map((u) => ({
      id: u.id,
      name: u.name,
      email: u.email ?? null,
      phone: u.phone ?? null,
    }));
  } catch {
    recipients = [];
  }

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
        <AdminBroadcastForm recipients={recipients} />
      </div>
    </div>
  );
}
