"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Avatar } from "@/components/ui/Avatar";
import { Badge, StatusBadge } from "@/components/ui/Badge";
import { Icon } from "@/components/ui/Icon";
import type {
  AdminUserDetail,
  AdminUserSubscription,
  AdminUserSubscriptionLifecycle,
  AdminUserWorkspace,
} from "@/lib/api/admin";
import { invoiceMoney } from "@/components/features/invoices/format";
import { adminDate, nameInitial } from "./format";

export interface UserDetailProps {
  user: AdminUserDetail;
  locale: string;
  backLabel: string;
}

/**
 * Super-admin single-user view. A common profile header plus a role-branched
 * body: a freelancer's subscriptions, an owner's workspace(s), or nothing extra
 * for an admin. RTL-correct via the project's logical-property utilities.
 */
export function UserDetail({ user, locale, backLabel }: UserDetailProps) {
  const t = useTranslations("admin.userDetail");

  return (
    <div className="page">
      <Link href="/admin/users" className="row muted" style={{ gap: 6, marginBottom: 14, width: "fit-content" }}>
        <Icon name="arrowL" size={16} />
        {backLabel}
      </Link>

      <Header user={user} locale={locale} t={t} />

      <div className="stack" style={{ gap: 20, marginTop: 20 }}>
        {user.role === "freelancer" && <FreelancerBody user={user} locale={locale} t={t} />}
        {user.role === "workspace_owner" && <OwnerBody user={user} locale={locale} t={t} />}
      </div>
    </div>
  );
}

type T = ReturnType<typeof useTranslations>;

/** Inset surface used for the nested subscription / workspace rows. */
const SUNK_ROW: React.CSSProperties = {
  background: "var(--surface-2)",
  border: "1px solid var(--border)",
  borderRadius: "var(--r-md)",
  padding: "14px 16px",
};

/** Badge tone per derived subscription lifecycle stage. */
const LIFECYCLE_TONE: Record<
  AdminUserSubscriptionLifecycle,
  "success" | "info" | "neutral" | "danger"
> = {
  active: "success",
  upcoming: "info",
  expired: "neutral",
  cancelled: "neutral",
  suspended: "danger",
};

/** Common header: avatar, name/email/phone, role + status badges, joined date. */
function Header({ user, locale, t }: { user: AdminUserDetail; locale: string; t: T }) {
  return (
    <section className="card card-pad">
      <div className="row wrap" style={{ gap: 18, alignItems: "flex-start" }}>
        <Avatar initial={nameInitial(user.name)} size="lg" round />
        <div className="stack" style={{ gap: 8, flex: 1, minWidth: 220 }}>
          <div className="row wrap" style={{ gap: 10, alignItems: "center" }}>
            <h1 className="h2" style={{ margin: 0 }}>{user.name}</h1>
            <Badge tone="info">{t(`role.${user.role}`)}</Badge>
            <StatusBadge status={user.status} locale={locale} />
          </div>
          <div className="row wrap muted-3" style={{ gap: 16, fontSize: "var(--fs-sm)" }}>
            <span className="row" style={{ gap: 6 }}>
              <Icon name="mail" size={15} />
              <span className="ltr">{user.email}</span>
            </span>
            {user.phone && (
              <span className="row" style={{ gap: 6 }}>
                <Icon name="phone" size={15} />
                <span className="ltr">{user.phone}</span>
              </span>
            )}
            <span className="row" style={{ gap: 6 }}>
              <Icon name="calendar" size={15} />
              {t("joined")}: <span className="ltr">{adminDate(user.created_at)}</span>
            </span>
          </div>
          {user.specialty && (
            <div className="muted" style={{ fontSize: "var(--fs-sm)" }}>
              {t("specialty")}: {user.specialty}
            </div>
          )}
          {user.bio && <p className="muted" style={{ fontSize: "var(--fs-sm)", margin: 0 }}>{user.bio}</p>}
        </div>
      </div>
    </section>
  );
}

/** Freelancer body: a subscription-status panel plus optional recent bookings. */
function FreelancerBody({ user, locale, t }: { user: AdminUserDetail; locale: string; t: T }) {
  const summary = user.subscriptions_summary ?? {
    total: 0,
    active: 0,
    upcoming: 0,
    expired: 0,
    cancelled: 0,
  };
  const subscriptions = user.subscriptions ?? [];
  const bookings = user.recent_bookings ?? [];

  return (
    <>
      <section className="card card-pad stack" style={{ gap: 14 }}>
        <div className="between row wrap" style={{ alignItems: "center", gap: 10 }}>
          <h3 className="h3" style={{ margin: 0 }}>{t("subscriptions.title")}</h3>
          <div className="row wrap" style={{ gap: 8 }}>
            <Badge tone="neutral">{t("subscriptions.totalCount", { count: summary.total })}</Badge>
            <Badge tone="success">{t("subscriptions.activeCount", { count: summary.active })}</Badge>
            {summary.upcoming > 0 && (
              <Badge tone="info">{t("subscriptions.upcomingCount", { count: summary.upcoming })}</Badge>
            )}
            {summary.expired > 0 && (
              <Badge tone="neutral">{t("subscriptions.expiredCount", { count: summary.expired })}</Badge>
            )}
            {summary.cancelled > 0 && (
              <Badge tone="neutral">{t("subscriptions.cancelledCount", { count: summary.cancelled })}</Badge>
            )}
          </div>
        </div>

        {subscriptions.length === 0 ? (
          <p className="muted" style={{ fontSize: "var(--fs-sm)", margin: 0 }}>
            {t("subscriptions.empty")}
          </p>
        ) : (
          <div className="stack" style={{ gap: 12 }}>
            {subscriptions.map((s) => (
              <SubscriptionRow key={s.id} sub={s} locale={locale} t={t} />
            ))}
          </div>
        )}
      </section>

      {bookings.length > 0 && (
        <section className="card card-pad stack" style={{ gap: 12 }}>
          <h3 className="h3" style={{ margin: 0 }}>{t("bookings.title")}</h3>
          <div className="stack" style={{ gap: 8 }}>
            {bookings.map((b) => (
              <div key={b.id} className="between row" style={{ gap: 10 }}>
                <span style={{ fontWeight: 500 }}>{b.workspace?.name ?? "—"}</span>
                <div className="row" style={{ gap: 10 }}>
                  <StatusBadge status={b.status} locale={locale} />
                  <span className="muted-3 cell-num ltr" style={{ fontSize: "var(--fs-xs)" }}>
                    {adminDate(b.created_at)}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </section>
      )}
    </>
  );
}

/** A single subscription card: lifecycle, workspace, plan, price and date span. */
function SubscriptionRow({ sub, locale, t }: { sub: AdminUserSubscription; locale: string; t: T }) {
  return (
    <div className="stack" style={{ ...SUNK_ROW, gap: 10 }}>
      <div className="between row wrap" style={{ gap: 10 }}>
        <span style={{ fontWeight: 600 }}>{sub.workspace?.name ?? "—"}</span>
        <div className="row" style={{ gap: 8 }}>
          <Badge tone={LIFECYCLE_TONE[sub.lifecycle]} dot>
            {t(`subscriptions.lifecycle.${sub.lifecycle}`)}
          </Badge>
          <StatusBadge status={sub.status} locale={locale} />
        </div>
      </div>
      <div className="row wrap muted-3" style={{ gap: 18, fontSize: "var(--fs-sm)" }}>
        {sub.workspace?.city && (
          <Field label={t("subscriptions.city")}>{sub.workspace.city}</Field>
        )}
        {sub.seat_number && (
          <Field label={t("subscriptions.seat")}>
            <span className="cell-num ltr">{sub.seat_number}</span>
          </Field>
        )}
        <Field label={t("subscriptions.plan")}>{t(`plan.${sub.plan_type}`)}</Field>
        <Field label={t("subscriptions.price")}>
          <span className="cell-num ltr">{invoiceMoney(sub.monthly_price)}</span>
        </Field>
        <Field label={t("subscriptions.start")}>
          <span className="cell-num ltr">{adminDate(sub.start_date)}</span>
        </Field>
        <Field label={t("subscriptions.end")}>
          <span className="cell-num ltr">{adminDate(sub.end_date)}</span>
        </Field>
      </div>
    </div>
  );
}

/** Owner body: their workspace(s) plus uploaded verification documents. */
function OwnerBody({ user, locale, t }: { user: AdminUserDetail; locale: string; t: T }) {
  const workspaces = user.workspaces ?? [];
  const documents = user.documents;
  const hasDocs = !!(documents && (documents.license_file || documents.id_document));

  return (
    <>
      <section className="card card-pad stack" style={{ gap: 14 }}>
        <h3 className="h3" style={{ margin: 0 }}>{t("workspaces.title")}</h3>
        {workspaces.length === 0 ? (
          <p className="muted" style={{ fontSize: "var(--fs-sm)", margin: 0 }}>
            {t("workspaces.empty")}
          </p>
        ) : (
          <div className="stack" style={{ gap: 12 }}>
            {workspaces.map((w) => (
              <WorkspaceRow key={w.id} workspace={w} locale={locale} t={t} />
            ))}
          </div>
        )}
      </section>

      {hasDocs && (
        <section className="card card-pad stack" style={{ gap: 12 }}>
          <h3 className="h3" style={{ margin: 0 }}>{t("documents.title")}</h3>
          <div className="row wrap" style={{ gap: 10 }}>
            {documents?.license_file && (
              <DocLink href={documents.license_file} label={t("documents.license")} />
            )}
            {documents?.id_document && (
              <DocLink href={documents.id_document} label={t("documents.id")} />
            )}
          </div>
        </section>
      )}
    </>
  );
}

/** A single owned workspace: headline figures, seat availability and pricing. */
function WorkspaceRow({ workspace, locale, t }: { workspace: AdminUserWorkspace; locale: string; t: T }) {
  const seatTypes = workspace.seat_types ?? [];
  const seats = workspace.seats ?? { total: workspace.total_seats, occupied: 0, available: workspace.total_seats };

  return (
    <div className="stack" style={{ ...SUNK_ROW, gap: 12 }}>
      <div className="between row wrap" style={{ gap: 10 }}>
        <Link href={`/admin/workspaces?search=${encodeURIComponent(workspace.name)}`} style={{ fontWeight: 600 }}>
          {workspace.name}
        </Link>
        <StatusBadge status={workspace.status} locale={locale} />
      </div>

      <div className="row wrap muted-3" style={{ gap: 18, fontSize: "var(--fs-sm)" }}>
        <Field label={t("workspaces.city")}>{workspace.city}</Field>
        <Field label={t("workspaces.price")}>
          <span className="cell-num ltr">{invoiceMoney(workspace.price_per_month)}</span>
        </Field>
        {workspace.avg_rating != null && (
          <Field label={t("workspaces.rating")}>
            <span className="cell-num ltr">{workspace.avg_rating}</span>
          </Field>
        )}
        <Field label={t("workspaces.created")}>
          <span className="cell-num ltr">{adminDate(workspace.created_at)}</span>
        </Field>
      </div>

      <div className="row wrap" style={{ gap: 10 }}>
        <SeatStat label={t("workspaces.seatsTotal")} value={seats.total} tone="neutral" />
        <SeatStat label={t("workspaces.seatsOccupied")} value={seats.occupied} tone="warning" />
        <SeatStat label={t("workspaces.seatsAvailable")} value={seats.available} tone="success" />
      </div>

      {seatTypes.length > 0 && (
        <div className="stack" style={{ gap: 6 }}>
          <span className="muted-3" style={{ fontSize: "var(--fs-xs)", fontWeight: 600 }}>
            {t("workspaces.seatTypes.title")}
          </span>
          <div className="tbl-scroll" style={{ border: "1px solid var(--border)", borderRadius: "var(--r-md)" }}>
            <table className="tbl" style={{ minWidth: 420 }}>
              <thead>
                <tr>
                  <th>{t("workspaces.seatTypes.type")}</th>
                  <th>{t("workspaces.seatTypes.monthly")}</th>
                  <th>{t("workspaces.seatTypes.daily")}</th>
                  <th>{t("workspaces.seatTypes.capacity")}</th>
                  <th>{t("workspaces.seatTypes.enabled")}</th>
                </tr>
              </thead>
              <tbody>
                {seatTypes.map((seatType) => (
                  <tr key={seatType.id}>
                    <td>{t(`workspaces.seatTypes.types.${seatType.type}`)}</td>
                    <td className="cell-num ltr">{invoiceMoney(seatType.monthly_price)}</td>
                    <td className="cell-num ltr">{invoiceMoney(seatType.daily_price)}</td>
                    <td className="cell-num ltr">{seatType.capacity}</td>
                    <td>
                      <Badge tone={seatType.enabled ? "success" : "neutral"}>
                        {seatType.enabled ? t("workspaces.seatTypes.on") : t("workspaces.seatTypes.off")}
                      </Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}

/** A compact total/occupied/available seat counter pill. */
function SeatStat({
  label,
  value,
  tone,
}: {
  label: string;
  value: number;
  tone: "neutral" | "warning" | "success";
}) {
  return (
    <div
      className="stack"
      style={{
        gap: 2,
        padding: "8px 14px",
        borderRadius: "var(--r-md)",
        border: "1px solid var(--border)",
        background: "var(--surface-1)",
        minWidth: 92,
      }}
    >
      <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>{label}</span>
      <div className="row" style={{ gap: 6, alignItems: "center" }}>
        <Badge tone={tone} dot>
          <span className="cell-num ltr">{value}</span>
        </Badge>
      </div>
    </div>
  );
}

/** An inline "label: value" pair used inside the detail cards. */
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <span className="row" style={{ gap: 6 }}>
      <span style={{ color: "var(--text-3)" }}>{label}:</span>
      <span style={{ color: "var(--text-2)", fontWeight: 500 }}>{children}</span>
    </span>
  );
}

/** A button-styled external link to a stored document (opens in a new tab). */
function DocLink({ href, label }: { href: string; label: string }) {
  return (
    <a href={href} target="_blank" rel="noopener noreferrer" className="btn btn-secondary btn-sm">
      <Icon name="doc" size={16} />
      {label}
    </a>
  );
}
