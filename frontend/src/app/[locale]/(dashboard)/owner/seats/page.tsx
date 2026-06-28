import { getTranslations, setRequestLocale } from "next-intl/server";
import { getOwnerWorkspace } from "@/lib/actions/owner";
import { ownerSeats } from "@/lib/api/seats";
import { listMembers } from "@/lib/api/members";
import { Icon } from "@/components/ui/Icon";
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
          <div className="empty-state" style={{ minHeight: 280 }}>
            <div className="st-ico amber" style={{ width: 48, height: 48 }}>
              <Icon name="grid" />
            </div>
            <div>
              <div className="h3">{t("noSeatsTitle")}</div>
              <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
                {t("noSeatsBody")}
              </div>
            </div>
          </div>
        ) : (
          <SeatBoard seats={seats} members={memberResult.members} />
        )}
      </div>
    </div>
  );
}
