/**
 * Shared backend entity types for the dashboard area.
 *
 * Mirrors the Laravel API Resources (envelope `{ data }`). All primary keys are
 * UUID strings. Re-exports the auth types so a single import point exists.
 */
export type {
  User,
  UserRole,
  UserStatus,
  AdminRole,
  AdminPermission,
  ApiEnvelope,
  ApiErrorBody,
} from "./auth";

export type {
  LocalizedText,
  LandingHero,
  LandingStat,
  FeaturedSection,
  WhySection,
  CapabilitiesSection,
  TestimonialsSection,
  LandingSections,
  LandingTestimonial,
  LandingContent,
  ReorderableSectionKey,
} from "./landing";
export { DEFAULT_SECTIONS_ORDER, sanitizeSectionsOrder } from "./landing";

export type {
  SmtpEncryption,
  SmsProvider,
  MaskedSmtpConfig,
  MaskedSmsConfig,
  PlatformMessagingConfig,
  WorkspaceMessagingConfig,
  SmtpUpdateInput,
  SmsUpdateInput,
  PlatformMessagingUpdateInput,
  WorkspaceMessagingUpdateInput,
  MessagingTestChannel,
  BroadcastChannel,
  BroadcastAudience,
  BroadcastInput,
  BroadcastChannelCounts,
  BroadcastResult,
} from "./messaging";
import type { WorkspaceMessagingConfig } from "./messaging";

export type {
  ContentKey,
  SiteSocial,
  SiteContent,
  FaqItem,
  FaqContent,
  AboutSection,
  AboutContent,
  HowItWorksStep,
  HowItWorksContent,
} from "./content";

/* -------------------------------------------------------------------------- */
/*  Enums (string-backed, mirror App\Enums\*)                                  */
/* -------------------------------------------------------------------------- */

export type SeatType = "fixed" | "flexible" | "private_office";
export type SeatStatus = "available" | "occupied" | "reserved" | "maintenance";
export type PlanType = "monthly" | "daily" | "custom";
export type SubscriptionStatus =
  | "active"
  | "cancelled"
  | "expired"
  | "pending"
  | "suspended";
export type BookingStatus = "pending" | "approved" | "rejected";
export type InvoiceStatus = "pending" | "paid" | "overdue" | "cancelled";
export type WorkspaceStatus = "pending" | "active" | "suspended" | "rejected";

/* -------------------------------------------------------------------------- */
/*  Pagination                                                                 */
/* -------------------------------------------------------------------------- */

/** Laravel `LengthAwarePaginator` meta block. */
export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  path: string;
  per_page: number;
  to: number | null;
  total: number;
  links?: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

/** Standard Laravel resource-collection pagination envelope. */
export interface Paginated<T> {
  data: T[];
  links: PaginationLinks;
  meta: PaginationMeta;
}

/** Compact meta used by some owner endpoints (members, reviews). */
export interface SimpleMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

/* -------------------------------------------------------------------------- */
/*  Workspace                                                                  */
/* -------------------------------------------------------------------------- */

export interface WorkspaceOwnerSummary {
  id: string;
  name: string;
  email: string;
  phone: string | null;
}

export interface SeatsSummary {
  total: number;
  available: number;
  occupied: number;
  maintenance: number;
}

/**
 * Per-seat-type pricing row, mirroring `WorkspaceResource::seat_types`.
 *
 * Money fields arrive as decimal strings (e.g. `"120.00"`) or `null` when unset.
 * The public side only renders `enabled` rows; the owner side edits all three.
 */
export interface SeatTypePrice {
  type: SeatType;
  price_monthly: string | null;
  price_daily: string | null;
  capacity: number;
  enabled: boolean;
}

export interface RecentReview {
  rating: number;
  comment: string | null;
  reviewer: string;
  created_at: string | null;
}

export interface Workspace {
  id: string;
  owner_id: string;
  name: string;
  description: string | null;
  address: string;
  city: string;
  phone: string | null;
  latitude: number | null;
  longitude: number | null;
  total_seats: number;
  price_per_month: number;
  amenities: string[];
  photos: string[];
  working_hours: Record<string, unknown> | null;
  status: WorkspaceStatus;
  /**
   * Public-publish gate, separate from `status`. Present only for requests that
   * may manage the workspace (its owner or an admin); absent on public payloads.
   * `is_published` is true once an admin has published it to public discovery;
   * `published_at` is the ISO timestamp of that action (null when unpublished).
   */
  is_published?: boolean;
  published_at?: string | null;
  avg_rating: number | null;
  /**
   * Per-seat-type pricing. All three types may be present; disabled ones are
   * included (hide them on the public side). Absent on legacy rows.
   */
  seat_types?: SeatTypePrice[];
  created_at: string | null;
  /** Present on the public detail endpoint only. */
  seats_summary?: SeatsSummary;
  recent_reviews?: RecentReview[];
  /** Present on the admin listing only. */
  owner?: WorkspaceOwnerSummary;
  /**
   * Masked messaging config. Present only for requests that may manage the
   * workspace (its owner or an admin); absent on public discovery responses.
   */
  messaging?: WorkspaceMessagingConfig;
}

/* -------------------------------------------------------------------------- */
/*  Seat                                                                       */
/* -------------------------------------------------------------------------- */

export interface SeatAssignedMember {
  id: string;
  name: string;
  avatar_url: string | null;
}

export interface Seat {
  id: string;
  workspace_id: string;
  seat_number: string;
  type: SeatType;
  status: SeatStatus;
  notes: string | null;
  /** Only present for the owner/admin (loaded conditionally). */
  assigned_member?: SeatAssignedMember | null;
  created_at: string | null;
}

/* -------------------------------------------------------------------------- */
/*  Subscription + invoices                                                    */
/* -------------------------------------------------------------------------- */

export interface InvoiceLine {
  id: string;
  invoice_number: string;
  amount: string;
  status: InvoiceStatus;
  due_date: string | null;
  paid_at: string | null;
}

export interface SubscriptionWorkspaceSummary {
  id: string;
  name: string;
  city: string;
  address: string;
  photos: string[];
}

export interface SubscriptionSeatSummary {
  id: string;
  seat_number: string;
  type: SeatType;
  status: SeatStatus;
}

export interface Subscription {
  id: string;
  workspace_id: string;
  seat_id: string | null;
  plan_type: PlanType;
  start_date: string | null;
  end_date: string | null;
  monthly_price: number;
  status: SubscriptionStatus;
  cancelled_at: string | null;
  created_at: string | null;
  workspace?: SubscriptionWorkspaceSummary;
  seat?: SubscriptionSeatSummary | null;
  invoices?: InvoiceLine[];
}

/* -------------------------------------------------------------------------- */
/*  Member (owner perspective)                                                 */
/* -------------------------------------------------------------------------- */

export interface MemberUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  specialty: string | null;
  avatar: string | null;
}

/** A workspace member as seen by the owner (a Subscription row, flattened). */
export interface Member {
  subscription_id: string;
  user: MemberUser;
  status: SubscriptionStatus;
  plan_type: PlanType;
  seat_number: string | null;
  monthly_price: number;
  join_date: string | null;
  cancelled_at: string | null;
}

/** Owner members listing payload: `{ members, meta }`. */
export interface MemberListResult {
  members: Member[];
  meta: SimpleMeta;
}

export interface MemberDetailUser extends MemberUser {
  bio: string | null;
}

export interface MemberDetailSubscription {
  id: string;
  status: SubscriptionStatus;
  plan_type: PlanType;
  monthly_price: number;
  start_date: string | null;
  end_date: string | null;
  cancelled_at: string | null;
}

export interface MemberDetailInvoice {
  id: string;
  invoice_number: string;
  amount: string;
  status: InvoiceStatus;
  due_date: string | null;
  paid_at: string | null;
}

export interface MemberDetail {
  user: MemberDetailUser;
  subscription: MemberDetailSubscription;
  seat: SubscriptionSeatSummary | null;
  invoices: MemberDetailInvoice[];
  payment_history: MemberDetailInvoice[];
}

/* -------------------------------------------------------------------------- */
/*  Internet package                                                           */
/* -------------------------------------------------------------------------- */

export interface PackageMember {
  id: string;
  name: string;
  avatar: string | null;
  assigned_at: string | null;
}

export interface Package {
  id: string;
  workspace_id: string;
  name: string;
  speed_mbps: number;
  price: string;
  data_limit_gb: number | null;
  is_unlimited: boolean;
  is_active: boolean;
  assigned_members_count?: number;
  members?: PackageMember[];
  created_at: string | null;
  updated_at: string | null;
}

/* -------------------------------------------------------------------------- */
/*  Booking request                                                            */
/* -------------------------------------------------------------------------- */

export interface BookingMember {
  id: string;
  name: string;
  email: string;
  specialty: string | null;
  avatar: string | null;
}

export interface BookingRequest {
  id: string;
  workspace_id: string;
  preferred_seat_type: SeatType | null;
  message: string | null;
  status: BookingStatus;
  rejection_reason: string | null;
  reviewed_at: string | null;
  member?: BookingMember;
  created_at: string | null;
}

/* -------------------------------------------------------------------------- */
/*  Review                                                                     */
/* -------------------------------------------------------------------------- */

export interface Review {
  id: string;
  reviewer_name: string;
  rating: number;
  comment: string | null;
  created_at: string | null;
}

export interface ReviewListResult {
  reviews: Review[];
  meta: SimpleMeta;
}

/* -------------------------------------------------------------------------- */
/*  Dashboard stats                                                            */
/* -------------------------------------------------------------------------- */

export interface RevenueChartPoint {
  month: string;
  amount: number;
}

/** Owner dashboard aggregate (`GET /workspace/dashboard`). */
export interface OwnerStats {
  occupancy_pct: number;
  active_members: number;
  available_seats: number;
  pending_bookings: number;
  overdue_invoices: number;
  revenue_this_month: number;
  revenue_last_month: number;
  revenue_chart: RevenueChartPoint[];
}

export interface MemberSummarySubscription {
  status: SubscriptionStatus;
  workspace_name: string;
  plan_type: PlanType;
  end_date: string | null;
}

export interface MemberSummarySeat {
  seat_number: string;
  type: SeatType;
}

export interface MemberSummaryInvoice {
  amount: string;
  due_date: string | null;
}

/** Freelancer dashboard summary (`GET /member/dashboard`). */
export interface MemberSummary {
  subscription: MemberSummarySubscription | null;
  seat: MemberSummarySeat | null;
  next_invoice: MemberSummaryInvoice | null;
  unread_notifications: number;
  pending_booking_requests: number;
}
