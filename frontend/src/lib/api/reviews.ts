import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, Review, ReviewListResult } from "@/lib/types";

/** Public: paginated reviews for a workspace (`{ reviews, meta }`). */
export async function reviewsForWorkspace(
  workspaceId: string,
  params: { per_page?: number; page?: number } = {},
): Promise<ReviewListResult> {
  const search = new URLSearchParams();
  if (params.per_page) search.set("per_page", String(params.per_page));
  if (params.page) search.set("page", String(params.page));
  const qs = search.toString();

  const res = await serverFetch<ApiEnvelope<ReviewListResult>>(
    `/workspaces/${workspaceId}/reviews${qs ? `?${qs}` : ""}`,
    { auth: false },
  );
  return res.data;
}

export interface CreateReviewInput {
  workspace_id: string;
  rating: number;
  comment?: string | null;
}

/** Freelancer: submit a review for a workspace. */
export async function createReview(
  input: CreateReviewInput,
): Promise<Review> {
  const res = await serverFetch<ApiEnvelope<Review>>("/reviews", {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data;
}
