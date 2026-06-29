"use client";

import { useEffect, useState } from "react";

/**
 * "Needs your action" counters returned by `GET /api/nav/action-counts`.
 * Only the keys relevant to the caller's role are present; absent keys behave
 * as zero (no badge). See the backend `NavActionCountService`.
 */
export interface ActionCounts {
  /** Owner: pending booking requests in their workspace. */
  bookings?: number;
  /** Owner: invoices with an under-review receipt awaiting approve/reject. */
  receipts?: number;
  /** Admin: workspaces awaiting publish/approval (status pending). */
  workspaces?: number;
}

/**
 * Fetch the sidebar action-badge counts for the signed-in user once on mount
 * (re-running whenever the user identity changes). Returns an empty object
 * until loaded or when there is no user, so no badge renders. Failures are
 * swallowed — a badge is non-critical chrome and must never break the shell.
 */
export function useActionCounts(userId: string | undefined): ActionCounts {
  const [counts, setCounts] = useState<ActionCounts>({});

  useEffect(() => {
    if (!userId) return;

    let active = true;

    void (async () => {
      try {
        const res = await fetch("/api/nav/action-counts", {
          credentials: "include",
        });
        if (!res.ok) return;

        const body: { data?: ActionCounts } = await res.json();
        if (active && body.data) setCounts(body.data);
      } catch {
        // Non-critical: leave counts empty so the sidebar renders badge-free.
      }
    })();

    return () => {
      active = false;
    };
  }, [userId]);

  // No signed-in user → no action queue, so report empty regardless of any
  // stale fetched value (avoids leaking the previous user's counts after logout).
  return userId ? counts : {};
}
