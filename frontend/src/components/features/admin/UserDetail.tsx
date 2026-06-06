"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Avatar } from "@/components/ui/Avatar";
import { Badge, StatusBadge } from "@/components/ui/Badge";
import { Icon } from "@/components/ui/Icon";
import type {
  AdminUserDetail,
  AdminUserSubscription,
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
  const summary = user.subscriptions_summary ?? { total: 0, active: 0 };
  const subscriptions = user.subscriptions ?? [];
  const bookings = user.recent_bookings ?? [];

  return (
    <>
      <section className="card card-pad stack" style={{ gap: 14 }}>
        <div className="between" style={{ alignItems: "center" }}>
          <h3 className="h3" style={{ margin: 0 }}>{t("subscriptions.title")}</h3>
          <div className="row" style={{ gap: 8 }}>
            <Badge tone="success">{t("subscriptions.activeCount", { count: summary.active })}</Badge>
            <Badge tone="neutral">{t("subscriptions.totalCount", { count: summary.total })}</Badge>
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

/** A single subscription card: status, workspace, plan, price and date span. */
function SubscriptionRow({ sub, locale, t }: { sub: AdminUserSubscription; locale: string; t: T }) {
  return (
    <div className="stack" style={{ ...SUNK_ROW, gap: 10 }}>
      <div className="between row wrap" style={{ gap: 10 }}>
        <span style={{ fontWeight: 600 }}>{sub.workspace?.name ?? "—"}</span>
        <div className="row" style={{ gap: 8 }}>
          {sub.is_active ? (
            <Badge tone="success" dot>{t("subscriptions.active")}</Badge>
          ) : (
            <Badge tone="neutral" dot>{t("subscriptions.expired")}</Badge>
          )}
          <StatusBadge status={sub.status} locale={locale} />
        </div>
      </div>
      <div className="row wrap muted-3" style={{ gap: 18, fontSize: "var(--fs-sm)" }}>
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

/** A single owned workspace with its headline figures. */
function WorkspaceRow({ workspace, locale, t }: { workspace: AdminUserWorkspace; locale: string; t: T }) {
  return (
    <div className="stack" style={{ ...SUNK_ROW, gap: 10 }}>
      <div className="between row wrap" style={{ gap: 10 }}>
        <Link href={`/admin/workspaces?search=${encodeURIComponent(workspace.name)}`} style={{ fontWeight: 600 }}>
          {workspace.name}
        </Link>
        <StatusBadge status={workspace.status} locale={locale} />
      </div>
      <div className="row wrap muted-3" style={{ gap: 18, fontSize: "var(--fs-sm)" }}>
        <Field label={t("workspaces.city")}>{workspace.city}</Field>
        <Field label={t("workspaces.seats")}>
          <span className="cell-num ltr">{workspace.total_seats}</span>
        </Field>
        <Field label={t("workspaces.price")}>
          <span className="cell-num ltr">{invoiceMoney(workspace.price_per_month)}</span>
        </Field>
        <Field label={t("workspaces.created")}>
          <span className="cell-num ltr">{adminDate(workspace.created_at)}</span>
        </Field>
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
