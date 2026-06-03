import { serverFetch } from "@/lib/api";
import type {
  ApiEnvelope,
  Paginated,
  Workspace,
} from "@/lib/types";

/** Public discovery filters (`GET /workspaces`). */
export interface WorkspaceFilters {
  city?: string;
  min_price?: number;
  max_price?: number;
  amenities?: string[];
  min_rating?: number;
  search?: string;
  sort?: string;
  page?: number;
}

function toQuery(filters: WorkspaceFilters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value == null || value === "") continue;
    if (Array.isArray(value)) {
      value.forEach((v) => params.append(`${key}[]`, String(v)));
    } else {
      params.set(key, String(value));
    }
  }
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

/** Paginated public, active-only workspace discovery. */
export async function listWorkspaces(
  filters: WorkspaceFilters = {},
): Promise<Paginated<Workspace>> {
  const res = await serverFetch<ApiEnvelope<Paginated<Workspace>>>(
    `/workspaces${toQuery(filters)}`,
    { auth: false },
  );
  return res.data;
}

/** Distinct active cities for filter facets. */
export async function listCities(): Promise<string[]> {
  const res = await serverFetch<ApiEnvelope<string[]>>("/workspaces/cities", {
    auth: false,
  });
  return res.data;
}

/** Full public detail for a single workspace (includes seats_summary + reviews). */
export async function showWorkspace(id: string): Promise<Workspace> {
  const res = await serverFetch<ApiEnvelope<Workspace>>(`/workspaces/${id}`, {
    auth: false,
  });
  return res.data;
}

/** Owner: create the (single) workspace. */
export interface CreateWorkspaceInput {
  name: string;
  description?: string | null;
  address: string;
  city: string;
  phone?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  total_seats: number;
  price_per_month: number;
  amenities?: string[];
  working_hours?: Record<string, unknown> | null;
}

export async function createWorkspace(
  input: CreateWorkspaceInput,
): Promise<Workspace> {
  const res = await serverFetch<ApiEnvelope<Workspace>>("/workspace/create", {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data;
}

export type UpdateWorkspaceInput = Partial<CreateWorkspaceInput>;

/** Owner: update workspace settings. */
export async function updateWorkspaceSettings(
  input: UpdateWorkspaceInput,
): Promise<Workspace> {
  const res = await serverFetch<ApiEnvelope<Workspace>>("/workspace/settings", {
    method: "PUT",
    body: JSON.stringify(input),
  });
  return res.data;
}

/** Owner: upload workspace photos (multipart). */
export async function uploadWorkspacePhotos(
  formData: FormData,
): Promise<Workspace> {
  const res = await serverFetch<ApiEnvelope<Workspace>>("/workspace/photos", {
    method: "POST",
    body: formData,
  });
  return res.data;
}

/** Owner: remove a workspace photo by its stored path. */
export async function deleteWorkspacePhoto(path: string): Promise<Workspace> {
  const res = await serverFetch<ApiEnvelope<Workspace>>("/workspace/photos", {
    method: "DELETE",
    body: JSON.stringify({ path }),
  });
  return res.data;
}
