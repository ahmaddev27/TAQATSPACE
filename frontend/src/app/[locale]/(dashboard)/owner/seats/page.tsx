import { getTranslations, setRequestLocale } from "next-intl/server";
import { getOwnerWorkspace } from "@/lib/actions/owner";
import { ownerSeats } from "@/lib/api/seats";
import { listMembers } from "@/lib/api/members";
import { EmptyState } from "@/components/ui/EmptyState";
import { SeatBoard } from "@/components/features/owner/SeatBoard";

export const dynamic = "force-dynamic";

export default async function OwnerSeatsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.seats");

  const workspace = await getOwnerWorkspace();

  const [seats, memberResult] = await Promise.all([
    workspace ? ownerSeats() : Promise.resolve([]),
    listMembers({ status: "active", per_page: 200 }),
  ]);

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
        {seats.length === 0 ? (
          <EmptyState
            icon="grid"
            title={t("noSeatsTitle")}
            body={t("noSeatsBody")}
            action={{
              label: t("noSeatsAction"),
              href: "/owner/settings",
              variant: "secondary",
            }}
          />
        ) : (
          <SeatBoard seats={seats} members={memberResult.members} />
        )}
      </div>
    </div>
  );
}
