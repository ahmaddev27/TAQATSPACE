import { getTranslations, setRequestLocale } from "next-intl/server";
import { getOwnerWorkspace } from "@/lib/actions/owner";
import { listBookingsForOwner } from "@/lib/api/bookings";
import { seatsForWorkspace } from "@/lib/api/seats";
import { listPackages } from "@/lib/api/packages";
import { BookingsManager } from "@/components/features/owner/BookingsManager";
import type { BookingRequest, Package, Seat } from "@/lib/types";

export const dynamic = "force-dynamic";

export default async function OwnerBookingsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("owner.bookings");

  const workspace = await getOwnerWorkspace();

  const [pending, approved, rejected, seats, packages] = await Promise.all([
    listBookingsForOwner({ status: "pending", per_page: 200 }),
    listBookingsForOwner({ status: "approved", per_page: 200 }),
    listBookingsForOwner({ status: "rejected", per_page: 200 }),
    workspace
      ? seatsForWorkspace(workspace.id)
      : Promise.resolve<Seat[]>([]),
    listPackages().catch(() => [] as Package[]),
  ]);

  const bookings: BookingRequest[] = [
    ...pending.data,
    ...approved.data,
    ...rejected.data,
  ];
  const availableSeats = seats.filter((s) => s.status === "available");
  const activePackages = packages.filter((p) => p.is_active);

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
        <BookingsManager
          bookings={bookings}
          availableSeats={availableSeats}
          packages={activePackages}
        />
      </div>
    </div>
  );
}
