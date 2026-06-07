"use server";

import { revalidatePath } from "next/cache";
import { authedMutate, type ActionResult } from "@/lib/actions/client";
import type {
  Branding,
  BroadcastInput,
  BroadcastResult,
  ContentKey,
  LandingContent,
  MessagingTestChannel,
  PlatformMessagingConfig,
  PlatformMessagingUpdateInput,
  User,
  UserStatus,
  Workspace,
  WorkspaceStatus,
} from "@/lib/types";
import type { ContentByKey } from "@/lib/api/content";
import type { AdminInvoice } from "@/lib/api/admin";

/**
 * Persist the public landing page content (`PUT /admin/landing`).
 *
 * On success, revalidate both the admin editor page and the public landing
 * page (across locales via the `[locale]` layout segment) so the new copy is
 * served immediately.
 */
export async function updateLanding(
  content: LandingContent,
): Promise<ActionResult<LandingContent>> {
  const result = await authedMutate<LandingContent>("/admin/landing", {
    method: "PUT",
    body: content,
  });

  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/admin/landing", "page");
    revalidatePath("/[locale]/(public)", "page");
  }

  return result;
}

/** A stored landing-section image: canonical path (saved) + display URL. */
export interface LandingImageUpload {
  path: string;
  url: string;
}

/**
 * Upload a single landing-section image (`POST /admin/landing/images`).
 *
 * Returns the canonical storage `path` (persisted into the section's `image`
 * field via {@link updateLanding}) and a resolved `url` for immediate preview.
 * Uploading does not itself publish anything — the admin must still save.
 */
export async function uploadLandingImage(
  file: File,
): Promise<ActionResult<LandingImageUpload>> {
  const formData = new FormData();
  formData.append("image", file);

  return authedMutate<LandingImageUpload>("/admin/landing/images", {
    method: "POST",
    formData,
  });
}

/* -------------------------------------------------------------------------- */
/*  Site branding (PUT /admin/branding, POST /admin/branding/images)           */
/* -------------------------------------------------------------------------- */

/**
 * Persist the site branding (`PUT /admin/branding`): site name, SEO meta, and
 * the favicon / light / dark logo paths.
 *
 * Branding is read in the root `[locale]` layout (favicon + meta + logo CSS
 * vars), so on success we revalidate the whole locale layout — that flushes
 * every shell (public, dashboard, auth) at once. Image fields carry the
 * canonical path returned by {@link uploadBrandingImage}.
 */
export async function updateBranding(
  branding: Branding,
): Promise<ActionResult<Branding>> {
  const result = await authedMutate<Branding>("/admin/branding", {
    method: "PUT",
    body: branding,
  });

  if (result.ok) {
    revalidatePath("/[locale]", "layout");
  }

  return result;
}

/** A stored branding asset: canonical path (saved) + display URL (preview). */
export interface BrandingImageUpload {
  path: string;
  url: string;
}

/**
 * Upload a single branding asset — a favicon or a light/dark logo
 * (`POST /admin/branding/images`).
 *
 * Returns the canonical storage `path` (persisted into branding via
 * {@link updateBranding}) and a resolved `url` for immediate preview.
 * Uploading does not itself publish anything — the admin must still save.
 */
export async function uploadBrandingImage(
  file: File,
): Promise<ActionResult<BrandingImageUpload>> {
  const formData = new FormData();
  formData.append("image", file);

  return authedMutate<BrandingImageUpload>("/admin/branding/images", {
    method: "POST",
    formData,
  });
}

/**
 * Public path each content key feeds into. The home page (`/[locale]/(public)`)
 * also reads `how_it_works`, and the footer (shared layout) reads `site`, so
 * those keys revalidate the public segment root in addition to their own route.
 */
const CONTENT_REVALIDATE: Record<ContentKey, string[]> = {
  site: ["/[locale]/(public)", "/[locale]/(public)/contact"],
  faq: ["/[locale]/(public)/faq"],
  about: ["/[locale]/(public)/about"],
  how_it_works: ["/[locale]/(public)"],
};

/**
 * Persist a site-wide content blob (`PUT /admin/content/{key}`).
 *
 * The backend expects the blob wrapped as `{ content }`. On success we
 * revalidate the admin CRM hub plus every public path that renders this key so
 * the new copy is served immediately.
 */
export async function updateContent<K extends ContentKey>(
  key: K,
  content: ContentByKey[K],
): Promise<ActionResult<ContentByKey[K]>> {
  const result = await authedMutate<ContentByKey[K]>(`/admin/content/${key}`, {
    method: "PUT",
    body: { content },
  });

  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/admin/crm", "page");
    for (const path of CONTENT_REVALIDATE[key]) {
      revalidatePath(path, "page");
    }
  }

  return result;
}

/* -------------------------------------------------------------------------- */
/*  Workspace moderation (PUT /admin/workspaces/{id}/status)                   */
/* -------------------------------------------------------------------------- */

/**
 * Approve, suspend, reactivate, or reject a workspace. The backend requires a
 * `reason` for the `suspended`/`rejected` transitions and rejects `pending`, so
 * callers pass a reason for those two and may omit it when approving.
 */
export async function updateWorkspaceStatus(
  workspaceId: string,
  status: WorkspaceStatus,
  reason?: string,
): Promise<ActionResult<Workspace>> {
  const body: Record<string, unknown> = { status };
  if (reason != null && reason !== "") body.reason = reason;

  const result = await authedMutate<Workspace>(
    `/admin/workspaces/${workspaceId}/status`,
    { method: "PUT", body },
  );
  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/admin/workspaces", "page");
    revalidatePath("/[locale]/(dashboard)/admin", "page");
  }
  return result;
}

/* -------------------------------------------------------------------------- */
/*  User moderation (PUT /admin/users/{id}/status)                            */
/* -------------------------------------------------------------------------- */

/** Activate or suspend a user account. */
export async function updateUserStatus(
  userId: string,
  status: UserStatus,
): Promise<ActionResult<User>> {
  const result = await authedMutate<User>(`/admin/users/${userId}/status`, {
    method: "PUT",
    body: { status },
  });
  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/admin/users", "page");
    revalidatePath("/[locale]/(dashboard)/admin", "page");
  }
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Invoice tracking (PUT mark-paid / mark-unpaid, POST receipt)               */
/* -------------------------------------------------------------------------- */

function revalidateInvoiceTracking(): void {
  revalidatePath("/[locale]/(dashboard)/admin/invoices", "page");
  revalidatePath("/[locale]/(dashboard)/admin", "page");
}

/**
 * Record a manual payment, optionally attaching the payment receipt in the same
 * step. When a receipt file is provided the request is sent as multipart so the
 * backend stores the file and flips the invoice to paid in one action; otherwise
 * a plain JSON body carries just the optional paid date.
 */
export async function markInvoicePaid(
  invoiceId: string,
  paidAt?: string | null,
  receipt?: File | null,
): Promise<ActionResult<AdminInvoice>> {
  const path = `/admin/invoices/${invoiceId}/mark-paid`;

  let result: ActionResult<AdminInvoice>;
  if (receipt) {
    const formData = new FormData();
    formData.append("receipt", receipt);
    if (paidAt != null && paidAt !== "") formData.append("paid_at", paidAt);
    result = await authedMutate<AdminInvoice>(path, { method: "PUT", formData });
  } else {
    const body = paidAt != null && paidAt !== "" ? { paid_at: paidAt } : undefined;
    result = await authedMutate<AdminInvoice>(path, { method: "PUT", body });
  }

  if (result.ok) revalidateInvoiceTracking();
  return result;
}

/** Revert an invoice back to pending and clear its payment timestamp. */
export async function markInvoiceUnpaid(
  invoiceId: string,
): Promise<ActionResult<AdminInvoice>> {
  const result = await authedMutate<AdminInvoice>(
    `/admin/invoices/${invoiceId}/mark-unpaid`,
    { method: "PUT" },
  );
  if (result.ok) revalidateInvoiceTracking();
  return result;
}

/**
 * Upload a payment receipt (multipart). The backend stores the file and marks
 * the invoice paid in the same action, so this doubles as a "mark paid with
 * proof" flow. An optional paid date is forwarded when provided.
 */
export async function uploadInvoiceReceipt(
  invoiceId: string,
  file: File,
  paidAt?: string | null,
): Promise<ActionResult<AdminInvoice>> {
  const formData = new FormData();
  formData.append("receipt", file);
  if (paidAt != null && paidAt !== "") formData.append("paid_at", paidAt);

  const result = await authedMutate<AdminInvoice>(
    `/admin/invoices/${invoiceId}/receipt`,
    { method: "POST", formData },
  );
  if (result.ok) revalidateInvoiceTracking();
  return result;
}

/* -------------------------------------------------------------------------- */
/*  Platform messaging settings (PUT + POST test)                              */
/* -------------------------------------------------------------------------- */

/**
 * Persist Taqat's own SMTP + SMS configuration (`PUT /admin/settings/messaging`).
 *
 * Secrets are write-only: a blank/omitted password or SMS credential preserves
 * the stored value, so callers send only the secrets the admin actually typed.
 * The backend returns the refreshed masked config (with `has_*` flags).
 */
export async function updatePlatformMessaging(
  input: PlatformMessagingUpdateInput,
): Promise<ActionResult<PlatformMessagingConfig>> {
  const result = await authedMutate<PlatformMessagingConfig>(
    "/admin/settings/messaging",
    { method: "PUT", body: input },
  );
  if (result.ok) {
    revalidatePath("/[locale]/(dashboard)/admin/settings/messaging", "page");
  }
  return result;
}

/**
 * Send a test email or SMS through the saved platform config
 * (`POST /admin/settings/messaging/test`). The backend never throws on a
 * driver error — it reports it as a 422 with a friendly message, surfaced here
 * as `ActionResult.message`.
 */
export async function sendMessagingTest(
  channel: MessagingTestChannel,
  to: string,
): Promise<ActionResult> {
  return authedMutate("/admin/settings/messaging/test", {
    method: "POST",
    body: { channel, to },
  });
}

/**
 * Compose and broadcast an email and/or SMS to a platform audience
 * (`POST /admin/messaging/broadcast`). The send is queued; the result reports
 * how many recipients were queued/skipped per channel. A channel whose account
 * is not configured is rejected as a 422 with a friendly message.
 */
export async function sendAdminBroadcast(
  input: BroadcastInput,
): Promise<ActionResult<BroadcastResult>> {
  return authedMutate<BroadcastResult>("/admin/messaging/broadcast", {
    method: "POST",
    body: input,
  });
}
