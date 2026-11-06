export type RecalculationState = "idle" | "queued" | "running" | "completed" | "failed";

/** Where the workspace's statistics recalculation stands (owner Tools tab). */
export interface RecalculationStatus {
  state: RecalculationState;
  /** True while the page should keep polling. */
  active: boolean;
  from: string | null;
  to: string | null;
  requested_at: string | null;
  finished_at: string | null;
  written: { days: number; subscriber_months: number } | null;
  /** True when the figures were written but the follow-up scoring failed. */
  scoring_failed?: boolean;
}

export interface RecalculateInput {
  from: string;
  to: string;
}
