import { listCities, listWorkspaces, type WorkspaceFilters } from "@/lib/api/workspaces";
import type { Workspace } from "@/lib/types";
import { getPublicDict, interpolate } from "@/components/features/public/i18n";
import { FilterBar } from "@/components/features/public/FilterBar";
import { ExploreResults } from "@/components/features/public/ExploreResults";

export const dynamic = "force-dynamic";

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
  try {
    const [page, cityList] = await Promise.all([listWorkspaces(filters), listCities()]);
    workspaces = page.data;
    cities = cityList;
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

      <ExploreResults workspaces={workspaces} dict={dict} locale={locale} />
    </>
  );
}
