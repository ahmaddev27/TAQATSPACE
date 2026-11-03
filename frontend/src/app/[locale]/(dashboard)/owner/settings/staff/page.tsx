import { setRequestLocale, getTranslations } from "next-intl/server";
import { ownerStaff } from "@/lib/api/staff";
import { StaffManager } from "@/components/features/owner/StaffManager";

export const dynamic = "force-dynamic";

export default async function StaffSettingsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.staff");
  const { staff, invitations } = await ownerStaff();

  return (
    <div className="page">
      <div className="page-head">
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>
      <StaffManager staff={staff} invitations={invitations} />
    </div>
  );
}
