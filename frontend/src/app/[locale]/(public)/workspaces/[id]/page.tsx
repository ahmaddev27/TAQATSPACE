import type { Metadata } from "next";
import { Link } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";
import { Rating } from "@/components/ui/Rating";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { ApiError } from "@/lib/api";
import { showWorkspace } from "@/lib/api/workspaces";
import { reviewsForWorkspace } from "@/lib/api/reviews";
import type { Review, Workspace } from "@/lib/types";
import { getPublicDict } from "@/components/features/public/i18n";
import { Gallery } from "@/components/features/public/Gallery";
import { AmenityGrid } from "@/components/features/public/AmenityGrid";
import { WorkingHours } from "@/components/features/public/WorkingHours";
import { SeatPricing } from "@/components/features/public/SeatPricing";
import { ReviewList } from "@/components/features/public/ReviewList";
import { DetailTabs } from "@/components/features/public/DetailTabs";
import { BookingPanel } from "@/components/features/public/BookingPanel";
import { DetailMapClient } from "@/components/features/public/DetailMapClient";
import { photoUrl, toNumber } from "@/components/features/public/helpers";

export const dynamic = "force-dynamic";

async function loadWorkspace(id: string): Promise<Workspace | null> {
  try {
    return await showWorkspace(id);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string; id: string }>;
}): Promise<Metadata> {
  const { locale, id } = await params;
  const workspace = await loadWorkspace(id);
  if (!workspace) {
    return { title: "TAQAT.space" };
  }
  const cityLine = workspace.city ? ` · ${workspace.city}` : "";
  const title = `${workspace.name}${cityLine} | TAQAT.space`;
  const description =
    workspace.description ??
    (locale === "en"
      ? `Book a seat at ${workspace.name} in ${workspace.city} on TAQAT.space.`
      : `احجز مقعدك في ${workspace.name} بمدينة ${workspace.city} عبر منصّة طاقت.`);

  return {
    title,
    description,
    openGraph: { title, description, type: "website" },
  };
}

export default async function WorkspaceDetailPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string; id: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const { locale, id } = await params;
  const sp = await searchParams;
  // Deep-linked from a partner (e.g. Academy) with `?book=1` → open the booking action.
  const autoBook = sp.book === "1";
  const dict = getPublicDict(locale);
  const d = dict.detail;
  const c = dict.common;

  const workspace = await loadWorkspace(id);

  if (!workspace) {
    return (
      <div className="container" style={{ padding: "80px 0 120px", textAlign: "center" }}>
        <div className="confirm-art" style={{ background: "var(--surface-2)", color: "var(--text-3)" }}>
          <Icon name="building" size={30} />
        </div>
        <h1 className="h1" style={{ marginTop: 18 }}>
          {d.notFound}
        </h1>
        <p className="muted" style={{ marginTop: 8 }}>
          {d.notFoundHint}
        </p>
        <div style={{ marginTop: 22 }}>
          <Link href="/explore">
            <Button variant="primary" iconEnd="arrowR">
              {d.backToExplore}
            </Button>
          </Link>
        </div>
      </div>
    );
  }

  let reviews: Review[] = [];
  let reviewsTotal = 0;
  try {
    const res = await reviewsForWorkspace(id, { per_page: 10 });
    reviews = res.reviews;
    reviewsTotal = res.meta.total;
  } catch {
    reviews = workspace.recent_reviews?.map((r, i) => ({
      id: `recent-${i}`,
      reviewer_name: r.reviewer,
      rating: r.rating,
      comment: r.comment,
      created_at: r.created_at,
    })) ?? [];
    reviewsTotal = reviews.length;
  }

  const price = toNumber(workspace.price_per_month);
  const rating = toNumber(workspace.avg_rating);
  const photos = (workspace.photos ?? []).map(photoUrl);
  const lat = workspace.latitude != null ? toNumber(workspace.latitude) : null;
  const lng = workspace.longitude != null ? toNumber(workspace.longitude) : null;
  const seats = workspace.seats_summary;

  const overview = (
    <div className="stack" style={{ gap: 24, marginTop: 24 }}>
      {workspace.description && <p style={{ lineHeight: 1.8 }}>{workspace.description}</p>}

      {seats && (
        <div className="row wrap" style={{ gap: 12 }}>
          <span className="badge badge-success">
            <span className="dot" />
            {seats.available} {d.availableSeats}
          </span>
          <span className="badge badge-info">
            {workspace.total_seats} {d.totalSeats}
          </span>
        </div>
      )}

      <AmenityGrid amenities={workspace.amenities ?? []} locale={locale} dict={dict} />

      <WorkingHours hours={workspace.working_hours} dict={dict} />

      {lat != null && lng != null && (
        <div>
          <h3 className="h3" style={{ marginBottom: 14 }}>
            {d.location}
          </h3>
          <DetailMapClient lat={lat} lng={lng} label={workspace.name} />
        </div>
      )}
    </div>
  );

  const seatsTab = (
    <SeatPricing
      seatTypes={workspace.seat_types}
      monthlyPrice={price}
      dict={dict}
    />
  );
  const reviewsTab = (
    <ReviewList reviews={reviews} avgRating={rating} total={reviewsTotal} dict={dict} />
  );

  return (
    <div className="container" style={{ paddingTop: 24 }}>
      <div className="crumbs" style={{ marginBottom: 16 }}>
        <Link href="/">{d.home}</Link>
        <Icon name="chevR" className="chev" size={14} />
        <Link href="/explore">{d.explore}</Link>
        <Icon name="chevR" className="chev" size={14} />
        <span style={{ color: "var(--text)" }}>{workspace.name}</span>
      </div>

      <Gallery photos={photos} alt={workspace.name} />

      <div className="detail-split">
        <div className="detail-main">
          <div className="between" style={{ alignItems: "flex-start" }}>
            <div>
              <div className="row" style={{ gap: 12, alignItems: "center" }}>
                <Avatar
                  initial={workspace.name.charAt(0)}
                  src={workspace.logo}
                  alt={workspace.name}
                  size="lg"
                  round
                />
                <h1 className="h1">{workspace.name}</h1>
              </div>
              <div className="row muted" style={{ gap: 8, marginTop: 8 }}>
                <span className="row" style={{ gap: 5 }}>
                  <Icon name="pin" size={16} />
                  {workspace.city}
                  {workspace.address ? ` · ${workspace.address}` : ""}
                </span>
                {rating > 0 && (
                  <>
                    <span>·</span>
                    <Rating
                      value={rating}
                      reviews={reviewsTotal || undefined}
                      reviewsLabel={c.reviews}
                    />
                  </>
                )}
              </div>
            </div>
          </div>

          <DetailTabs dict={dict} overview={overview} seats={seatsTab} reviews={reviewsTab} />
        </div>

        <BookingPanel
          workspaceId={workspace.id}
          price={price}
          seatTypes={workspace.seat_types}
          dict={dict}
          autoBook={autoBook}
        />
      </div>
    </div>
  );
}
