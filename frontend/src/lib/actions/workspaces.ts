"use server";

import { listWorkspaces, type WorkspaceFilters } from "@/lib/api/workspaces";
import type { Workspace } from "@/lib/types";

/**
 * Fetch one more page of public discovery results for the explore "load more"
 * button. Runs server-side (the public list is unauthenticated) and reports
 * whether further pages remain.
 */
export async function fetchMoreWorkspaces(
  filters: WorkspaceFilters,
  page: number,
): Promise<{ workspaces: Workspace[]; hasMore: boolean }> {
  const result = await listWorkspaces({ ...filters, page });
  return {
    workspaces: result.data,
    hasMore: result.meta.current_page < result.meta.last_page,
  };
}
