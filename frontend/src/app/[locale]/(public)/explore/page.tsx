import type { Metadata } from "next";
import { listWorkspaces, type WorkspaceFilters } from "@/lib/api/workspaces";
import { getCities } from "@/lib/api/cities";
import type { Workspace } from "@/lib/types";
import { getPublicDict, interpolate } from "@/components/features/public/i18n";
import { FilterBar } from "@/components/features/public/FilterBar";
import { ExploreResults } from "@/components/features/public/ExploreResults";

export const dynamic = "force-dynamic";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  return {
    title: locale === "ar" ? "استكشف المساحات" : "Explore spaces",
    description:
      locale === "ar"
        ? "تصفّح مساحات العمل المشتركة في غزة حسب المدينة والسعر والمرافق."
        : "Browse coworking spaces across Gaza by city, price and amenities.",
  };
}

type RawSearchParams = Record<string, string | string[] | undefined>;

function first(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

function asArray(value: string | string[] | undefined): string[] {
  if (value == null) return [];
  return Array.isArray(value) ? value : [value];
}

function toFilters(sp: RawSearchParams): WorkspaceFilters {
  const filters: WorkspaceFilters = {};
  const search = first(sp.search);
  const city = first(sp.city);
  const minPrice = first(sp.min_price);
  const maxPrice = first(sp.max_price);
  const minRating = first(sp.min_rating);
  const sort = first(sp.sort);
  const amenities = asArray(sp.amenities).filter(Boolean);

  if (search) filters.search = search;
  if (city) filters.city = city;
  if (minPrice && !Number.isNaN(Number(minPrice))) filters.min_price = Number(minPrice);
  if (maxPrice && !Number.isNaN(Number(maxPrice))) filters.max_price = Number(maxPrice);
  if (minRating && !Number.isNaN(Number(minRating))) filters.min_rating = Number(minRating);
  if (amenities.length) filters.amenities = amenities;
  filters.sort = sort ?? "rating";
  return filters;
}

export default async function ExplorePage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<RawSearchParams>;
}) {
  const [{ locale }, sp] = await Promise.all([params, searchParams]);
  const dict = getPublicDict(locale);
  const filters = toFilters(sp);

  let workspaces: Workspace[] = [];
  let cities: string[] = [];
  let hasMore = false;
  try {
    const [page, cityList] = await Promise.all([listWorkspaces(filters), getCities()]);
    workspaces = page.data;
    hasMore = page.meta.current_page < page.meta.last_page;
    // The backend filters by the workspace's denormalised `city` display name,
    // so the filter options are the cities' localised names (not their ids).
    cities = cityList.map((c) => (locale === "ar" ? c.name_ar : c.name_en));
  } catch {
    workspaces = [];
    cities = [];
  }

  return (
    <>
      <div className="explore-head">
        <div className="container">
          <h1 className="h1">{dict.explore.title}</h1>
          <p className="muted" style={{ marginTop: 4 }}>
            {interpolate(dict.explore.subtitle, { count: workspaces.length })}
          </p>
          <FilterBar dict={dict} cities={cities} locale={locale} seatType={first(sp.seat_type)} />
        </div>
      </div>

      <ExploreResults
        key={JSON.stringify(filters)}
        workspaces={workspaces}
        initialHasMore={hasMore}
        filters={filters}
        dict={dict}
        locale={locale}
      />
    </>
  );
}
